<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Formatting;

use St4rgaze\TelegramLogger\Enums\SegmentStyle;

final readonly class Segment
{
    /**
     * Create a run of plain text with one style.
     */
    public function __construct(
        public string $text,
        public SegmentStyle $style,
    ) {}
}
