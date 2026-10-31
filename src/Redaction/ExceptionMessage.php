<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Redaction;

use Illuminate\Database\QueryException;
use Throwable;

/**
 * @internal
 */
final class ExceptionMessage
{
    /**
     * Get an exception's message, describing a query exception with placeholder SQL instead of its bound values.
     */
    public static function of(Throwable $exception): string
    {
        if (! $exception instanceof QueryException) {
            return $exception->getMessage();
        }

        $previous = $exception->getPrevious();

        return sprintf(
            '%s (Connection: %s, SQL: %s)',
            $previous instanceof Throwable ? $previous->getMessage() : 'Query failed',
            $exception->getConnectionName(),
            $exception->getSql(),
        );
    }
}
