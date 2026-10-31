<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use St4rgaze\TelegramLogger\Delivery\FailureReporter;
use St4rgaze\TelegramLogger\Delivery\Jobs\SendTelegramMessage;
use St4rgaze\TelegramLogger\Telegram\TelegramClient;

beforeEach(function (): void {
    config([
        'telegram-logger.token' => TEST_TOKEN,
        'telegram-logger.chat_id' => TEST_CHAT_ID,
        'logging.channels.telegram' => ['driver' => 'telegram'],
    ]);
});

function runMessageJob(int $attempt = 1): SendTelegramMessage
{
    $job = (new SendTelegramMessage('telegram', '<b>hi</b>', true))->withFakeQueueInteractions();
    $job->job->attempts = $attempt;
    $job->chained = ['next-job'];

    $job->handle(app(TelegramClient::class), app(FailureReporter::class));

    return $job;
}

it('sends the chunk', function (): void {
    $http = fakeTelegram(['*' => Http::response(['ok' => true, 'result' => []])]);

    runMessageJob()->assertNotFailed()->assertNotReleased();

    $http->assertSentCount(1);
});

it('waits for retry_after on a 429', function (int $retryAfter, int $delay): void {
    fakeTelegram(['*' => Http::response(['ok' => false, 'description' => 'Too Many Requests', 'parameters' => ['retry_after' => $retryAfter]], 429)]);

    runMessageJob()->assertReleased($delay);
})->with([[17, 17], [9999, 300]]);

it('backs off on server errors while attempts remain', function (int $attempt, int $delay): void {
    fakeTelegram(['*' => Http::response('down', 502)]);

    runMessageJob($attempt)->assertReleased($delay)->assertNotFailed();
})->with([[1, 10], [2, 30]]);

it('reports, stops the chain and deletes on the final attempt', function (): void {
    fakeTelegram(['*' => fn () => throw new ConnectionException('down')]);

    $log = captureErrorLog(function () use (&$job): void {
        $job = runMessageJob(3);
    });

    $job->assertDeleted()->assertNotFailed()->assertNotReleased();
    expect($job->chained)->toBe([])
        ->and($log)->toContain('Could not connect to the Telegram API');
});

it('does not retry a client error', function (): void {
    fakeTelegram(['*' => Http::response(['ok' => false, 'description' => 'Bad Request: chat not found'], 400)]);

    $log = captureErrorLog(function () use (&$job): void {
        $job = runMessageJob();
    });

    $job->assertDeleted()->assertNotReleased()->assertNotFailed();
    expect($job->chained)->toBe([])
        ->and($log)->toContain('chat not found');
});

it('deletes the job when the channel is no longer configured', function (): void {
    config(['telegram-logger.token' => null]);
    $http = fakeTelegram();

    $log = captureErrorLog(fn () => runMessageJob()->assertDeleted()->assertNotFailed());

    $http->assertNothingSent();
    expect($log)->toContain('no bot token or chat id');
});

it('deletes the job when the channel config became invalid', function (): void {
    config(['logging.channels.telegram' => ['driver' => 'telegram', 'delivery' => 'nope']]);
    fakeTelegram();

    $log = captureErrorLog(fn () => runMessageJob()->assertDeleted()->assertNotFailed());

    expect($log)->toContain('[delivery]');
});

it('carries no token or context in its payload', function (): void {
    expect(serialize(new SendTelegramMessage('telegram', '<b>hi</b>', false)))->not->toContain(TEST_TOKEN)->not->toContain('123456');
});

it('reports instead of releasing on the sync queue, where a release is never retried', function (): void {
    config(['queue.default' => 'sync']);
    fakeTelegram(['*' => Http::response('down', 502)]);

    $log = captureErrorLog(fn () => SendTelegramMessage::dispatch('telegram', '<b>hi</b>', false));

    expect($log)->toContain('[telegram-logger] Delivery to chat *** failed:')->toContain('status 502');
});

it('dispatches immediately even inside a database transaction', function (): void {
    expect((new SendTelegramMessage('telegram', 'x', false))->afterCommit)->toBeFalse()
        ->and((new St4rgaze\TelegramLogger\Delivery\Jobs\SendTelegramDocument('telegram', new St4rgaze\TelegramLogger\Delivery\TraceFile('a', 'b', 'c'), false))->afterCommit)->toBeFalse();
});
