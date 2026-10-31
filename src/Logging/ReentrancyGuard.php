<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Logging;

use Closure;

/**
 * @internal
 */
final class ReentrancyGuard
{
    /**
     * Whether the package is currently sending or reporting.
     */
    private static bool $active = false;

    /**
     * Determine whether a send or report is in progress in this process.
     */
    public static function active(): bool
    {
        return self::$active;
    }

    /**
     * Run the callback with the guard active, restoring the previous state afterwards.
     *
     * @param  Closure(): mixed  $callback
     */
    public static function run(Closure $callback): void
    {
        $previous = self::$active;
        self::$active = true;

        try {
            $callback();
        } finally {
            self::$active = $previous;
        }
    }
}
