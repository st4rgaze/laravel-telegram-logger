<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Logging;

use DateTimeImmutable;
use Monolog\Level;

/**
 * A redacted, normalised log record handed to message formatters.
 */
final readonly class LogEntry
{
    /**
     * Create a log entry.
     *
     * @param  array<array-key, mixed>  $context
     * @param  array<array-key, mixed>  $extra
     * @param  list<ExceptionSummary>  $exceptions
     * @param  array<string, string>  $request
     */
    public function __construct(
        public Level $level,
        public string $channel,
        public string $message,
        public array $context,
        public array $extra,
        public DateTimeImmutable $datetime,
        public array $exceptions,
        public array $request,
        public int $suppressedCount,
        public string $appName,
        public string $environment,
    ) {}
}
