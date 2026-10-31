<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use St4rgaze\TelegramLogger\Delivery\FailureReporter;
use St4rgaze\TelegramLogger\Delivery\Jobs\SendTelegramDocument;
use St4rgaze\TelegramLogger\Delivery\TraceFile;
use St4rgaze\TelegramLogger\Telegram\TelegramClient;

beforeEach(function (): void {
    config(['telegram-logger.token' => TEST_TOKEN, 'telegram-logger.chat_id' => TEST_CHAT_ID]);
});

it('uploads the trace file', function (): void {
    $http = fakeTelegram(['*' => Http::response(['ok' => true, 'result' => []])]);
    $job = (new SendTelegramDocument('telegram', new TraceFile('trace.txt', 'trace', 'caption'), false))->withFakeQueueInteractions();

    $job->handle(app(TelegramClient::class), app(FailureReporter::class));

    $job->assertNotFailed()->assertNotReleased();
    $http->assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/sendDocument'));
});

it('backs off on server errors', function (): void {
    fakeTelegram(['*' => Http::response('down', 503)]);
    $job = (new SendTelegramDocument('telegram', new TraceFile('trace.txt', 'trace', 'caption'), false))->withFakeQueueInteractions();

    $job->handle(app(TelegramClient::class), app(FailureReporter::class));

    $job->assertReleased(10);
});
