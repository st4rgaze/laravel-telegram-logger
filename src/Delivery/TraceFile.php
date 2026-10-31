<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Delivery;

/**
 * @internal
 */
final readonly class TraceFile
{
    /**
     * Create a text attachment sent with sendDocument.
     */
    public function __construct(
        public string $filename,
        public string $contents,
        public string $caption,
    ) {}
}
