<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use St4rgaze\TelegramLogger\Delivery\Jobs\SendTelegramDocument;
use St4rgaze\TelegramLogger\Delivery\Jobs\SendTelegramMessage;

beforeEach(function (): void {
    config([
        'telegram-logger.token' => TEST_TOKEN,
        'telegram-logger.chat_id' => TEST_CHAT_ID,
        'telegram-logger.throttle.enabled' => false,
        'queue.default' => 'sync',
        'logging.channels.telegram' => ['driver' => 'telegram'],
    ]);
});

dataset('failures', [
    'connection' => [fn (): Closure => fn () => throw new ConnectionException('cURL error 28 for https://api.telegram.org/bot'.TEST_TOKEN.'/sendMessage')],
    '401' => [fn (): Closure => fn () => Http::response(['ok' => false, 'description' => 'Unauthorized'], 401)],
    '403' => [fn (): Closure => fn () => Http::response(['ok' => false, 'description' => 'Forbidden: bot '.TEST_TOKEN.' was blocked'], 403)],
    '429' => [fn (): Closure => fn () => Http::response(['ok' => false, 'description' => 'Too Many Requests', 'parameters' => ['retry_after' => 3]], 429)],
    '500' => [fn (): Closure => fn () => Http::response('upstream '.TEST_TOKEN, 500)],
    'invalid json' => [fn (): Closure => fn () => Http::response('<html>'.TEST_TOKEN, 200)],
]);

it('never leaks the token from a failed delivery', function (string $delivery, Closure $response): void {
    Event::fake([RequestSending::class]);
    config(['logging.channels.telegram.delivery' => $delivery]);
    $http = fakeTelegram(['*' => $response]);

    $log = captureErrorLog(fn () => Log::channel('telegram')->error('boom', ['exception' => new RuntimeException('x')]));

    $attempted = $http->recorded()->isNotEmpty() || str_contains($log, '[telegram-logger]');

    expect($attempted)->toBeTrue()
        ->and($log)->not->toContain(TEST_TOKEN);
    Event::assertNotDispatched(RequestSending::class);
})->with(['sync', 'queue'])->with('failures');

it('never puts the token in a queued payload', function (): void {
    Bus::fake();

    Log::channel('telegram')->error('boom', ['exception' => new RuntimeException('x'), 'api_key' => 'k']);

    $jobs = Bus::dispatched(SendTelegramMessage::class)->merge(Bus::dispatched(SendTelegramDocument::class));

    expect($jobs)->not->toBeEmpty();

    foreach ($jobs as $job) {
        expect(serialize($job))->not->toContain(TEST_TOKEN)->not->toContain('"k"');
    }
});
