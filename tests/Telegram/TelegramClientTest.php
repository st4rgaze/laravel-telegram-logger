<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use St4rgaze\TelegramLogger\Delivery\TraceFile;
use St4rgaze\TelegramLogger\Telegram\TelegramApiException;
use St4rgaze\TelegramLogger\Telegram\TelegramClient;

it('posts sendMessage as a json body with html parse mode', function (): void {
    $http = fakeTelegram(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

    $response = app(TelegramClient::class)->sendMessage(channelConfig(['message_thread_id' => 7]), '<b>hi</b>', true);

    expect($response->string('message_id'))->toBe('1');
    $http->assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://api.telegram.org/bot'.TEST_TOKEN.'/sendMessage'
        && $request['chat_id'] === TEST_CHAT_ID
        && $request['text'] === '<b>hi</b>'
        && $request['parse_mode'] === 'HTML'
        && $request['disable_notification'] === true
        && $request['message_thread_id'] === 7
        && $request['link_preview_options'] === ['is_disabled' => true]);
});

it('omits the thread id when none is configured', function (): void {
    $http = fakeTelegram(['*' => Http::response(['ok' => true, 'result' => []])]);

    app(TelegramClient::class)->sendMessage(channelConfig(), 'x', false);

    $http->assertSent(fn (Request $request): bool => ! array_key_exists('message_thread_id', $request->data()));
});

it('uploads the trace file with sendDocument as multipart', function (): void {
    $http = fakeTelegram(['*' => Http::response(['ok' => true, 'result' => []])]);

    app(TelegramClient::class)->sendDocument(
        channelConfig(['message_thread_id' => 7]),
        new TraceFile('trace.txt', 'stack trace', '🔴 ERROR'),
        false,
    );

    $http->assertSent(function (Request $request): bool {
        $parts = collect($request->data())->keyBy('name');

        return str_ends_with($request->url(), '/sendDocument')
            && $request->isMultipart()
            && $parts['document']['filename'] === 'trace.txt'
            && $parts['document']['contents'] === 'stack trace'
            && $parts['caption']['contents'] === '🔴 ERROR'
            && $parts['chat_id']['contents'] === TEST_CHAT_ID
            && $parts['message_thread_id']['contents'] === '7'
            && $parts['disable_notification']['contents'] === 'false';
    });
});

it('reads bot and chat details', function (): void {
    fakeTelegram([
        '*/getMe' => Http::response(['ok' => true, 'result' => ['username' => 'popout_bot', 'can_join_groups' => true]]),
        '*/getChat' => Http::response(['ok' => true, 'result' => ['title' => 'Ops', 'is_forum' => true]]),
    ]);
    $client = app(TelegramClient::class);

    expect($client->getMe(channelConfig())->string('username'))->toBe('popout_bot')
        ->and($client->getChat(channelConfig())->string('title'))->toBe('Ops')
        ->and($client->getChat(channelConfig())->bool('is_forum'))->toBeTrue()
        ->and($client->getChat(channelConfig())->bool('missing'))->toBeFalse()
        ->and($client->getChat(channelConfig())->string('missing'))->toBeNull();
});

it('uses the configured api host', function (): void {
    $http = fakeTelegram(['*' => Http::response(['ok' => true, 'result' => true])]);

    app(TelegramClient::class)->getMe(channelConfig(['api_host' => 'http://localhost:8081']));

    $http->assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'http://localhost:8081/bot'));
});

it('does not dispatch laravel http client events', function (): void {
    Event::fake([RequestSending::class, ResponseReceived::class]);
    fakeTelegram(['*' => Http::response(['ok' => true, 'result' => []])]);

    app(TelegramClient::class)->getMe(channelConfig());

    Event::assertNothingDispatched();
});

it('never exposes the token in exceptions', function (Closure $response): void {
    fakeTelegram(['*' => $response]);

    try {
        app(TelegramClient::class)->sendMessage(channelConfig(), 'x', false);
    } catch (TelegramApiException $telegramApiException) {
        expect($telegramApiException->getMessage())->not->toContain(TEST_TOKEN)
            ->and($telegramApiException->getPrevious())->toBeNull()
            ->and((string) $telegramApiException)->not->toContain(TEST_TOKEN);

        return;
    }

    $this->fail('Expected a TelegramApiException.');
})->with([
    'connection' => [fn (): Closure => fn () => throw new ConnectionException('cURL error 28 for https://api.telegram.org/bot'.TEST_TOKEN.'/sendMessage')],
    '401' => [fn (): Closure => fn () => Http::response(['ok' => false, 'error_code' => 401, 'description' => 'Unauthorized'], 401)],
    '403' => [fn (): Closure => fn () => Http::response(['ok' => false, 'description' => 'Forbidden: bot was blocked by the user'], 403)],
    '429' => [fn (): Closure => fn () => Http::response(['ok' => false, 'description' => 'Too Many Requests', 'parameters' => ['retry_after' => 17]], 429)],
    '500' => [fn (): Closure => fn () => Http::response('upstream '.TEST_TOKEN, 500)],
    'invalid json' => [fn (): Closure => fn () => Http::response('not json', 200)],
    'not ok' => [fn (): Closure => fn () => Http::response(['ok' => false, 'description' => 'nope'], 200)],
]);

it('masks the token in a description echoed by Telegram', function (): void {
    fakeTelegram(['*' => Http::response(['ok' => false, 'description' => 'Bad Request: bot '.TEST_TOKEN.' is invalid'], 400)]);

    expect(fn () => app(TelegramClient::class)->sendMessage(channelConfig(), 'x', false))
        ->toThrow(function (TelegramApiException $exception): void {
            expect($exception->getMessage())
                ->toContain('***')
                ->not->toContain(TEST_TOKEN)
                ->and($exception->description)->not->toContain(TEST_TOKEN);
        });
});

it('reads retry_after from a 429', function (): void {
    fakeTelegram(['*' => Http::response(['ok' => false, 'description' => 'Too Many Requests', 'parameters' => ['retry_after' => 17]], 429)]);

    expect(fn () => app(TelegramClient::class)->sendMessage(channelConfig(), 'x', false))
        ->toThrow(function (TelegramApiException $exception): void {
            expect($exception)
                ->status->toBe(429)
                ->retryAfter->toBe(17)
                ->description->toBe('Too Many Requests')
                ->and($exception->isRetryable())->toBeTrue();
        });
});

it('classifies which failures are retryable', function (Closure $response, bool $retryable): void {
    fakeTelegram(['*' => $response]);

    expect(fn () => app(TelegramClient::class)->sendMessage(channelConfig(), 'x', false))
        ->toThrow(function (TelegramApiException $exception) use ($retryable): void {
            expect($exception->isRetryable())->toBe($retryable);
        });
})->with([
    '400' => [fn (): Closure => fn () => Http::response(['ok' => false, 'description' => 'Bad Request: chat not found'], 400), false],
    '401' => [fn (): Closure => fn () => Http::response(['ok' => false, 'description' => 'Unauthorized'], 401), false],
    '502' => [fn (): Closure => fn () => Http::response('bad gateway', 502), true],
    'connection' => [fn (): Closure => fn () => throw new ConnectionException('down'), true],
]);
