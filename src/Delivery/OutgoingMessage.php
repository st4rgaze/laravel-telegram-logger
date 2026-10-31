<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Delivery;

/**
 * @internal
 */
final readonly class OutgoingMessage
{
    /**
     * Create a message ready for delivery.
     *
     * @param  list<string>  $chunks
     */
    public function __construct(
        public array $chunks,
        public ?TraceFile $trace,
        public bool $silent,
    ) {}
}
