<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Exceptions;

use InvalidArgumentException;

final class InvalidConfiguration extends InvalidArgumentException
{
    /**
     * Create an exception for a config value of the wrong type or range.
     */
    public static function invalidValue(string $key, string $expected): self
    {
        return new self(sprintf('Telegram logger config [%s] must be %s.', $key, $expected));
    }

    /**
     * Create an exception for an api_host that does not use https.
     */
    public static function insecureApiHost(): self
    {
        return new self('Telegram logger config [api_host] must use https:// (http:// is only allowed for localhost).');
    }

    /**
     * Create an exception for a queued channel whose name cannot be resolved.
     */
    public static function unnamedQueuedChannel(): self
    {
        return new self('A Telegram log channel built on demand cannot use queue delivery, because the queued job could not find its config again. Define it in config/logging.php, or set "delivery" to "sync".');
    }
}
