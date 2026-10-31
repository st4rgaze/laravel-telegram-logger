<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Diagnostics;

use St4rgaze\TelegramLogger\Enums\CheckStatus;

/**
 * @internal
 */
final readonly class Check
{
    /**
     * Create a check result.
     */
    public function __construct(
        public CheckStatus $status,
        public string $message,
    ) {}

    /**
     * Create a passing check.
     */
    public static function pass(string $message): self
    {
        return new self(CheckStatus::Pass, $message);
    }

    /**
     * Create a failing check.
     */
    public static function fail(string $message): self
    {
        return new self(CheckStatus::Fail, $message);
    }

    /**
     * Create a warning.
     */
    public static function warn(string $message): self
    {
        return new self(CheckStatus::Warn, $message);
    }
}
