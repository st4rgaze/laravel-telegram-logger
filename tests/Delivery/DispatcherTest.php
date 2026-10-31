<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use St4rgaze\TelegramLogger\Delivery\Dispatcher;
use St4rgaze\TelegramLogger\Delivery\Jobs\SendTelegramDocument;
use St4rgaze\TelegramLogger\Delivery\Jobs\SendTelegramMessage;
use St4rgaze\TelegramLogger\Delivery\OutgoingMessage;
use St4rgaze\TelegramLogger\Delivery\TraceFile;

function outgoing(): OutgoingMessage
{
    return new OutgoingMessage(['(1/2) a', '(2/2) b'], new TraceFile('trace.txt', 'trace', 'caption'), true);
}

it('queues the chunks and trace as one chain', function (): void {
    Bus::fake();

    app(Dispatcher::class)->dispatch(outgoing(), channelConfig(['queue' => ['connection' => 'redis', 'name' => 'alerts']]));

    Bus::assertChained([
        fn (SendTelegramMessage $job): bool => $job->html === '(1/2) a' && $job->silent && $job->channel === 'telegram'
            && $job->connection === 'redis' && $job->queue === 'alerts',
        fn (SendTelegramMessage $job): bool => $job->html === '(2/2) b',
        fn (SendTelegramDocument $job): bool => $job->file->filename === 'trace.txt',
    ]);
});

it('queues a single job without a trace', function (): void {
    Bus::fake();

    app(Dispatcher::class)->dispatch(new OutgoingMessage(['only'], null, false), channelConfig());

    Bus::assertDispatched(SendTelegramMessage::class, fn (SendTelegramMessage $job): bool => $job->html === 'only');
    Bus::assertNotDispatched(SendTelegramDocument::class);
});

it('sends in order when delivery is sync', function (): void {
    $http = fakeTelegram(['*' => Http::response(['ok' => true, 'result' => []])]);

    app(Dispatcher::class)->dispatch(outgoing(), channelConfig(['delivery' => 'sync']));

    $http->assertSentInOrder([
        fn (Request $request): bool => $request['text'] === '(1/2) a',
        fn (Request $request): bool => $request['text'] === '(2/2) b',
        fn (Request $request): bool => str_ends_with($request->url(), '/sendDocument'),
    ]);
});

it('stops and reports once when a sync send fails', function (): void {
    $http = fakeTelegram(['*' => Http::response(['ok' => false, 'description' => 'Forbidden'], 403)]);

    $log = captureErrorLog(fn () => app(Dispatcher::class)->dispatch(outgoing(), channelConfig(['delivery' => 'sync'])));

    $http->assertSentCount(1);
    expect(mb_substr_count($log, '[telegram-logger]'))->toBe(1);
});
