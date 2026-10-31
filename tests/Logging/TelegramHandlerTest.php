<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\BufferHandler;
use Monolog\Logger;
use St4rgaze\TelegramLogger\Delivery\Jobs\SendTelegramMessage;
use St4rgaze\TelegramLogger\Formatting\MessageFormatter;
use St4rgaze\TelegramLogger\Formatting\TelegramText;
use St4rgaze\TelegramLogger\Logging\LogEntry;
use St4rgaze\TelegramLogger\Logging\ReentrancyGuard;
use St4rgaze\TelegramLogger\Logging\TelegramHandler;

final class ExplodingFormatter implements MessageFormatter
{
    public function format(LogEntry $entry): TelegramText
    {
        throw new RuntimeException('formatter bug');
    }
}

function telegramLogger(array $config = []): Logger
{
    return new Logger('telegram', [app()->make(TelegramHandler::class, ['config' => channelConfig($config)])]);
}

beforeEach(function (): void {
    config(['telegram-logger.token' => TEST_TOKEN, 'telegram-logger.chat_id' => TEST_CHAT_ID]);
});

it('queues a message for a record', function (): void {
    Bus::fake();

    telegramLogger()->error('Payment failed');

    Bus::assertChained([fn (SendTelegramMessage $job): bool => str_contains($job->html, 'Payment failed')]);
});

it('does nothing without a token', function (): void {
    Bus::fake();

    telegramLogger(['token' => null])->error('Payment failed');

    Bus::assertNothingDispatched();
});

it('ignores records below the channel level', function (): void {
    Bus::fake();

    telegramLogger(['level' => 'error'])->warning('just a warning');

    Bus::assertNothingDispatched();
});

it('sends a duplicate only once', function (): void {
    Bus::fake();

    telegramLogger()->error('Payment failed');
    telegramLogger()->error('Payment failed');

    expect(Bus::dispatched(SendTelegramMessage::class))->toHaveCount(1);
});

it('ignores records logged while sending', function (): void {
    config(['logging.channels.telegram' => ['driver' => 'telegram', 'delivery' => 'sync']]);
    $http = fakeTelegram(['*' => function () {
        Log::channel('telegram')->error('logged from inside the send');

        return Http::response(['ok' => true, 'result' => []]);
    }]);

    Log::channel('telegram')->error('outer');

    $http->assertSentCount(1);
    expect(ReentrancyGuard::active())->toBeFalse();
});

it('reports an internal failure once and never throws', function (): void {
    Bus::fake();

    $log = captureErrorLog(fn () => telegramLogger(['formatter' => ExplodingFormatter::class])->error('Payment failed'));

    expect(mb_substr_count($log, '[telegram-logger]'))->toBe(1)
        ->and($log)->toContain('RuntimeException')
        ->not->toContain('formatter bug');
    Bus::assertNothingDispatched();
    expect(ReentrancyGuard::active())->toBeFalse();
});

it('sends a buffered batch as one message', function (): void {
    Bus::fake();
    $handler = app()->make(TelegramHandler::class, ['config' => channelConfig()]);
    $logger = new Logger('telegram', [new BufferHandler($handler)]);

    $logger->error('first');
    $logger->warning('second');
    $logger->debug('third');
    $logger->close();

    $jobs = Bus::dispatched(SendTelegramMessage::class);

    expect($jobs)->toHaveCount(1)
        ->and($jobs->first()->html)->toContain('first')->toContain('second')->toContain('third');
});

it('skips a batch with nothing to handle', function (): void {
    Bus::fake();
    $handler = app()->make(TelegramHandler::class, ['config' => channelConfig(['level' => 'critical'])]);

    $handler->handleBatch([logRecord(), logRecord()]);

    Bus::assertNothingDispatched();
});

it('never throws when a context value fails to serialise', function (): void {
    Bus::fake();
    $value = new class implements JsonSerializable
    {
        public function jsonSerialize(): mixed
        {
            throw new LogicException('lazy loading violation');
        }
    };

    $log = captureErrorLog(fn () => telegramLogger()->error('Payment failed', ['model' => $value]));

    expect($log)->toContain('[telegram-logger]');
});

it('never throws when a processor fails', function (): void {
    $handler = app()->make(TelegramHandler::class, ['config' => channelConfig()]);
    $handler->pushProcessor(fn () => throw new RuntimeException('processor bug'));

    $log = captureErrorLog(fn () => (new Logger('telegram', [$handler]))->error('Payment failed'));

    expect($log)->toContain('[telegram-logger]');
});

it('does not run a monolog formatter', function (): void {
    expect(app()->make(TelegramHandler::class, ['config' => channelConfig()]))
        ->not->toBeInstanceOf(Monolog\Handler\FormattableHandlerInterface::class);
});

it('rate limits by telegram messages, counting the trace file', function (): void {
    Bus::fake();
    $this->travelTo(now()->startOfMinute());

    foreach (range(1, 11) as $i) {
        telegramLogger()->error("failure {$i}", ['exception' => new RuntimeException("x{$i}")]);
    }

    $chains = Bus::dispatched(SendTelegramMessage::class);

    expect($chains)->toHaveCount(10)
        ->and($chains->filter(fn (SendTelegramMessage $job): bool => count($job->chained) === 1))->toHaveCount(10);
});

it('lets a large batch through as one message', function (): void {
    Bus::fake();
    $logger = new Logger('telegram', [new BufferHandler(app()->make(TelegramHandler::class, ['config' => channelConfig()]))]);

    foreach (range(1, 30) as $i) {
        $logger->error("problem {$i}");
    }

    $logger->close();

    expect(Bus::dispatched(SendTelegramMessage::class)->first()->html)->toContain('problem 30');
});
