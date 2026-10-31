<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Throttling;

/**
 * @internal
 */
final readonly class ThrottleDecision
{
    /**
     * Create a throttle decision.
     */
    public function __construct(
        public bool $allowed,
        public int $suppressedCount,
    ) {}
}
