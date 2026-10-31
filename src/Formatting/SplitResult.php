<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Formatting;

/**
 * @internal
 */
final readonly class SplitResult
{
    /**
     * Create the result of splitting a text into Telegram messages.
     *
     * @param  list<string>  $chunks
     */
    public function __construct(
        public array $chunks,
        public string $overflow,
    ) {}
}
