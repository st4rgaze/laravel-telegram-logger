<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Telegram;

/**
 * @internal
 */
final readonly class TelegramResponse
{
    /**
     * Create a successful Bot API response.
     *
     * @param  array<array-key, mixed>  $result
     */
    public function __construct(public array $result) {}

    /**
     * Get a scalar result field as a string.
     */
    public function string(string $key): ?string
    {
        $value = $this->result[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * Get a result field as a strict boolean.
     */
    public function bool(string $key): bool
    {
        return ($this->result[$key] ?? null) === true;
    }
}
