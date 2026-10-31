<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use St4rgaze\TelegramLogger\Delivery\Jobs\SendTelegramDocument;
use St4rgaze\TelegramLogger\Delivery\Jobs\SendTelegramMessage;

beforeEach(function (): void {
    config([
        'telegram-logger.token' => TEST_TOKEN,
        'telegram-logger.chat_id' => TEST_CHAT_ID,
        'logging.channels.telegram' => ['driver' => 'telegram', 'level' => 'error'],
    ]);
});

it('queues the message and trace for a logged exception', function (): void {
    Bus::fake();

    Log::channel('telegram')->error('Payment failed', ['exception' => new RuntimeException('Card declined'), 'password' => 'hunter2']);

    Bus::assertChained([
        fn (SendTelegramMessage $job): bool => str_contains($job->html, 'Payment failed') && str_contains($job->html, '[redacted]')
            && ! str_contains($job->html, 'hunter2'),
        fn (SendTelegramDocument $job): bool => str_contains($job->file->contents, 'RuntimeException: Card declined'),
    ]);
});

it('sends the message then the trace in sync mode', function (): void {
    config(['logging.channels.telegram.delivery' => 'sync']);
    $http = fakeTelegram(['*' => Http::response(['ok' => true, 'result' => []])]);

    Log::channel('telegram')->error('Payment failed', ['exception' => new RuntimeException('Card declined')]);

    $http->assertSentInOrder([
        fn (Request $request): bool => str_ends_with($request->url(), '/sendMessage'),
        fn (Request $request): bool => str_ends_with($request->url(), '/sendDocument'),
    ]);
});

it('runs the queued chain through the sync queue', function (): void {
    config(['queue.default' => 'sync']);
    $http = fakeTelegram(['*' => Http::response(['ok' => true, 'result' => []])]);

    Log::channel('telegram')->critical('Disk almost full');

    $http->assertSent(fn (Request $request): bool => str_contains((string) $request['text'], 'Disk almost full'));
});

final class BufferTelegramTap
{
    public function __invoke(Illuminate\Log\Logger $logger): void
    {
        $monolog = $logger->getLogger();

        $monolog->setHandlers(array_map(
            fn (Monolog\Handler\HandlerInterface $handler): Monolog\Handler\BufferHandler => new Monolog\Handler\BufferHandler($handler),
            $monolog->getHandlers(),
        ));
    }
}

it('sends one message per request with the README buffer tap', function (): void {
    Bus::fake();
    config(['logging.channels.telegram.tap' => [BufferTelegramTap::class]]);

    Log::channel('telegram')->error('first problem');
    Log::channel('telegram')->error('second problem');
    Log::channel('telegram')->getLogger()->close();

    $jobs = Bus::dispatched(SendTelegramMessage::class);

    expect($jobs)->toHaveCount(1)
        ->and($jobs->first()->html)->toContain('first problem')->toContain('second problem');
});
