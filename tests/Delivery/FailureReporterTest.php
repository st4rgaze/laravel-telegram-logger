<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use St4rgaze\TelegramLogger\Delivery\FailureReporter;
use St4rgaze\TelegramLogger\Logging\ReentrancyGuard;

it('writes a token-free warning to the fallback channel', function (): void {
    $logger = Mockery::mock(Psr\Log\LoggerInterface::class);
    $logger->shouldReceive('warning')->once()->withArgs(function (string $line): bool {
        expect(ReentrancyGuard::active())->toBeTrue();

        return $line === '[telegram-logger] Delivery to chat *** failed: status 401 for bot***';
    });
    Log::shouldReceive('channel')->once()->with('slack')->andReturn($logger);

    app(FailureReporter::class)->report(channelConfig(['fallback_channel' => 'slack']), 'status 401 for bot'.TEST_TOKEN);
});

it('uses error_log when the fallback is the failing channel itself', function (): void {
    $log = captureErrorLog(fn () => app(FailureReporter::class)->report(channelConfig(['fallback_channel' => 'telegram']), 'boom '.TEST_TOKEN));

    expect($log)
        ->toContain('[telegram-logger] Delivery to chat *** failed: boom ***')
        ->not->toContain(TEST_TOKEN);
});

it('uses error_log without a config', function (): void {
    $log = captureErrorLog(fn () => app(FailureReporter::class)->report(null, 'invalid config'));

    expect($log)->toContain('[telegram-logger] Delivery failed: invalid config');
});

it('falls back to error_log when the fallback channel throws', function (): void {
    Log::shouldReceive('channel')->andThrow(new RuntimeException('no such channel'));

    $log = captureErrorLog(fn () => app(FailureReporter::class)->report(channelConfig(['fallback_channel' => 'missing']), 'boom'));

    expect($log)->toContain('failed: boom');
});
