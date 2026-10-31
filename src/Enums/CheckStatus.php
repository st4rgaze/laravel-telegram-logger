<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Enums;

/**
 * @internal
 */
enum CheckStatus
{
    case Pass;
    case Fail;
    case Warn;

    /**
     * Get the symbol printed before the check's message.
     */
    public function symbol(): string
    {
        return match ($this) {
            self::Pass => '✓',
            self::Fail => '✗',
            self::Warn => '!',
        };
    }
}
