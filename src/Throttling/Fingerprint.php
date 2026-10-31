<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Throttling;

use Monolog\LogRecord;
use Throwable;

/**
 * @internal
 */
final class Fingerprint
{
    /**
     * Identify records that are duplicates of each other: same channel, level, message and exception site.
     */
    public static function for(LogRecord $record, string $channel): string
    {
        $exception = $record->context['exception'] ?? null;

        return sha1(implode("\0", [
            $channel,
            $record->level->getName(),
            $record->message,
            $exception instanceof Throwable ? $exception::class : '',
            $exception instanceof Throwable ? $exception->getFile() : '',
            $exception instanceof Throwable ? (string) $exception->getLine() : '',
        ]));
    }
}
