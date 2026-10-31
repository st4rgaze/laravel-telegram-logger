<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Telegram;

use RuntimeException;
use SensitiveParameter;

/**
 * A Bot API failure that never carries the bot token, the request URL, or a previous exception.
 */
final class TelegramApiException extends RuntimeException
{
    /**
     * Create the exception from already-sanitised details.
     */
    private function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?string $description = null,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }

    /**
     * Create an exception for an error response from the Bot API.
     */
    public static function fromResponse(int $status, ?string $description, ?int $retryAfter, #[SensitiveParameter] ?string $token): self
    {
        $description = $description === null ? null : SecretMasker::mask($description, $token);

        return new self(
            sprintf('Telegram API request failed with status %d: %s', $status, $description ?? 'no description'),
            $status,
            $description,
            $retryAfter,
        );
    }

    /**
     * Create an exception for a timeout or network failure.
     */
    public static function connectionFailed(): self
    {
        return new self('Could not connect to the Telegram API (timeout or network error).');
    }

    /**
     * Create an exception for a response body that is not a Bot API result.
     */
    public static function invalidResponse(int $status): self
    {
        return new self(sprintf('The Telegram API returned an invalid response with status %d.', $status), $status);
    }

    /**
     * Determine whether sending again later could succeed.
     */
    public function isRetryable(): bool
    {
        return $this->status === null || $this->status === 429 || $this->status >= 500;
    }
}
