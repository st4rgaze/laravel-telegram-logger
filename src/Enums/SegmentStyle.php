<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Enums;

enum SegmentStyle
{
    case Plain;
    case Bold;
    case Italic;
    case Code;
    case Pre;
    case Quote;

    /**
     * Get the opening HTML tag.
     */
    public function open(): string
    {
        return match ($this) {
            self::Plain => '',
            self::Bold => '<b>',
            self::Italic => '<i>',
            self::Code => '<code>',
            self::Pre => '<pre>',
            self::Quote => '<blockquote>',
        };
    }

    /**
     * Get the closing HTML tag.
     */
    public function close(): string
    {
        return $this === self::Plain ? '' : '</'.mb_substr($this->open(), 1);
    }
}
