<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory;
use Monolog\Level;
use Monolog\LogRecord;
use St4rgaze\TelegramLogger\Logging\ChannelConfig;
use St4rgaze\TelegramLogger\Logging\LogEntry;
use St4rgaze\TelegramLogger\Telegram\TelegramClient;
use Tests\TestCase;

pest()->extend(TestCase::class)->in(__DIR__);

const TEST_TOKEN = '123456:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdef1234';
const TEST_CHAT_ID = '-1001234567890';

/**
 * Build a channel config from the package defaults plus the given channel overrides.
 *
 * @param  array<string, mixed>  $overrides
 */
function channelConfig(array $overrides = [], string $name = 'telegram'): ChannelConfig
{
    /** @var array<string, mixed> $global */
    $global = config('telegram-logger');

    return ChannelConfig::from(
        array_merge($global, ['token' => TEST_TOKEN, 'chat_id' => TEST_CHAT_ID]),
        $overrides,
        $name,
    );
}

/**
 * Bind a TelegramClient backed by a faked, event-less HTTP factory and return the factory.
 *
 * @param  array<string, mixed>|Closure  $stubs
 */
function fakeTelegram(array|Closure $stubs = []): Factory
{
    $http = (new Factory)->fake($stubs);
    $http->preventStrayRequests();

    app()->instance(TelegramClient::class, new TelegramClient($http));

    return $http;
}

/**
 * Build a Monolog record for tests.
 *
 * @param  array<array-key, mixed>  $context
 * @param  array<array-key, mixed>  $extra
 */
function logRecord(Level $level = Level::Error, string $message = 'Payment failed', array $context = [], array $extra = []): LogRecord
{
    return new LogRecord(
        new DateTimeImmutable('2026-11-01 03:22:03', new DateTimeZone('UTC')),
        'telegram',
        $level,
        $message,
        $context,
        $extra,
    );
}

/**
 * Build a log entry for formatter tests; keys are LogEntry constructor argument names.
 *
 * @param  array<string, mixed>  $overrides
 */
function logEntry(array $overrides = []): LogEntry
{
    return new LogEntry(...array_merge([
        'level' => Level::Error,
        'channel' => 'telegram',
        'message' => 'Payment failed',
        'context' => [],
        'extra' => [],
        'datetime' => new DateTimeImmutable('2026-11-01 10:22:03', new DateTimeZone('Asia/Jakarta')),
        'exceptions' => [],
        'request' => [],
        'suppressedCount' => 0,
        'appName' => 'Popout',
        'environment' => 'production',
    ], $overrides));
}

/**
 * Run the callback with error_log() redirected to a temporary file and return what was written.
 */
function captureErrorLog(Closure $callback): string
{
    $file = (string) tempnam(sys_get_temp_dir(), 'tg-error-log');
    $previous = ini_set('error_log', $file);

    try {
        $callback();
    } finally {
        ini_set('error_log', (string) $previous);
    }

    $contents = (string) file_get_contents($file);
    unlink($file);

    return $contents;
}

/**
 * Run the callback with PHP recording string call arguments in stack traces, as development php.ini files do.
 *
 * @template TResult
 *
 * @param  Closure(): TResult  $callback
 * @return TResult
 */
function withTraceArguments(Closure $callback): mixed
{
    $ignoreArgs = ini_set('zend.exception_ignore_args', '0');
    $maxLength = ini_set('zend.exception_string_param_max_len', '15');

    try {
        return $callback();
    } finally {
        ini_set('zend.exception_ignore_args', (string) $ignoreArgs);
        ini_set('zend.exception_string_param_max_len', (string) $maxLength);
    }
}
