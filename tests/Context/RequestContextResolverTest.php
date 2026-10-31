<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use St4rgaze\TelegramLogger\Context\RequestContextResolver;

beforeEach(function (): void {
    Route::post('/checkout', fn (): array => app(RequestContextResolver::class)->resolve(channelConfig(request()->input('config', []))))
        ->name('checkout.store');
    Route::post('/unnamed', fn (): array => app(RequestContextResolver::class)->resolve(channelConfig()));
});

it('resolves the request details during an http request', function (): void {
    $this->actingAs(new GenericUser(['id' => 17]))
        ->post('/checkout?token=secret')
        ->assertExactJson([
            'url' => 'http://localhost/checkout',
            'method' => 'POST',
            'route' => 'checkout.store',
            'user_id' => '17',
        ]);
});

it('leaves out the query string', function (): void {
    expect($this->post('/checkout?email=budi@example.com')->json('url'))->toBe('http://localhost/checkout');
});

it('adds the ip only when enabled', function (): void {
    expect($this->post('/checkout', ['config' => ['request_context' => ['ip' => true]]])->json('ip'))->toBe('127.0.0.1');
});

it('skips disabled keys and a missing user', function (): void {
    $this->post('/checkout', ['config' => ['request_context' => ['url' => false, 'route' => false]]])
        ->assertExactJson(['method' => 'POST']);
});

it('falls back to the route uri when the route has no name', function (): void {
    expect($this->post('/unnamed')->json('route'))->toBe('unnamed');
});

it('returns nothing outside an http request', function (): void {
    expect(app(RequestContextResolver::class)->resolve(channelConfig()))->toBe([]);
});

it('shows the real path with secret-named parameters masked', function (string $route, string $path, string $expected): void {
    Route::post($route, fn (): array => app(RequestContextResolver::class)->resolve(channelConfig()));

    expect($this->post($path)->json('url'))->toBe('http://localhost'.$expected);
})->with([
    'plain id' => ['/orders/{order}', '/orders/123', '/orders/123'],
    'reset token' => ['/reset-password/{token}/{email}', '/reset-password/s3cr3tR3setT0ken/budi@example.com', '/reset-password/{token}/budi@example.com'],
    'name containing a key' => ['/invites/{invite_token}', '/invites/abc123', '/invites/{invite_token}'],
    'email verification hash' => ['/email/verify/{id}/{hash}', '/email/verify/7/9f86d081884c', '/email/verify/7/{hash}'],
    'signed route signature' => ['/download/{file}/{signature}', '/download/report.pdf/a1b2c3', '/download/report.pdf/{signature}'],
    'missing optional parameter' => ['/posts/{slug?}', '/posts', '/posts'],
    'root' => ['/', '/', ''],
]);
