<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Monolog\Logger;
use St4rgaze\TelegramLogger\Delivery\Jobs\SendTelegramMessage;
use St4rgaze\TelegramLogger\Exceptions\InvalidConfiguration;
use St4rgaze\TelegramLogger\Logging\CreateTelegramLogger;
use St4rgaze\TelegramLogger\Logging\TelegramHandler;

beforeEach(function (): void {
    config([
        'telegram-logger.token' => TEST_TOKEN,
        'telegram-logger.chat_id' => TEST_CHAT_ID,
        'logging.channels.ops' => ['driver' => 'telegram', 'chat_id' => '99'],
        'logging.channels.alerts' => ['driver' => 'stack', 'channels' => ['ops']],
    ]);
    Bus::fake();
});

it('registers the telegram driver', function (): void {
    $logger = Log::channel('ops')->getLogger();

    expect($logger)->toBeInstanceOf(Logger::class)
        ->and($logger->getName())->toBe('ops')
        ->and($logger->getHandlers()[0])->toBeInstanceOf(TelegramHandler::class);
});

it('resolves the channel name from the logging config', function (): void {
    Log::channel('ops')->error('x');

    Bus::assertDispatched(SendTelegramMessage::class, fn (SendTelegramMessage $job): bool => $job->channel === 'ops');
});

it('resolves the channel name inside a stack', function (): void {
    Log::channel('alerts')->error('x');

    Bus::assertDispatched(SendTelegramMessage::class, fn (SendTelegramMessage $job): bool => $job->channel === 'ops');
});

it('uses the logging channel key even when the channel sets a name', function (): void {
    config(['logging.channels.named' => ['driver' => 'telegram', 'name' => 'custom']]);

    Log::channel('named')->error('x');

    Bus::assertDispatched(SendTelegramMessage::class, fn (SendTelegramMessage $job): bool => $job->channel === 'named');
});

it('rejects a queued on-demand channel even when it has a name', function (): void {
    expect(fn () => app(CreateTelegramLogger::class)(['driver' => 'telegram', 'name' => 'deploys', 'chat_id' => '-999']))
        ->toThrow(InvalidConfiguration::class);
});

it('rejects an unnamed queued channel', function (): void {
    expect(fn () => app(CreateTelegramLogger::class)(['driver' => 'telegram']))->toThrow(InvalidConfiguration::class);
});

it('sends an unnamed sync channel', function (): void {
    $http = fakeTelegram(['*' => Http::response(['ok' => true, 'result' => []])]);

    Log::build(['driver' => 'telegram', 'delivery' => 'sync'])->error('on demand');

    $http->assertSent(fn (Request $request): bool => str_contains((string) $request['text'], 'on demand'));
});

it('rejects invalid channel config when the channel is created', function (): void {
    expect(fn () => app(CreateTelegramLogger::class)(['driver' => 'telegram', 'name' => 'x', 'delivery' => 'fast']))
        ->toThrow(InvalidConfiguration::class);
});
