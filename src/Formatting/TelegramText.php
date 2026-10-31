<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Formatting;

use St4rgaze\TelegramLogger\Enums\SegmentStyle;

/**
 * A message built from typed segments, rendered to valid Telegram HTML by the package.
 */
final class TelegramText
{
    /**
     * The segments in order.
     *
     * @var list<Segment>
     */
    private array $segments = [];

    /**
     * Start an empty text.
     */
    public static function make(): self
    {
        return new self;
    }

    /**
     * Append plain text.
     */
    public function text(string $text): self
    {
        return $this->segment($text, SegmentStyle::Plain);
    }

    /**
     * Append bold text.
     */
    public function bold(string $text): self
    {
        return $this->segment($text, SegmentStyle::Bold);
    }

    /**
     * Append italic text.
     */
    public function italic(string $text): self
    {
        return $this->segment($text, SegmentStyle::Italic);
    }

    /**
     * Append inline code.
     */
    public function code(string $text): self
    {
        return $this->segment($text, SegmentStyle::Code);
    }

    /**
     * Append a preformatted block.
     */
    public function pre(string $text): self
    {
        return $this->segment($text, SegmentStyle::Pre);
    }

    /**
     * Append a block quote.
     */
    public function quote(string $text): self
    {
        return $this->segment($text, SegmentStyle::Quote);
    }

    /**
     * Append line breaks.
     */
    public function line(int $count = 1): self
    {
        return $this->segment(str_repeat("\n", max(0, $count)), SegmentStyle::Plain);
    }

    /**
     * Append all segments of another text.
     */
    public function append(self $other): self
    {
        array_push($this->segments, ...$other->segments());

        return $this;
    }

    /**
     * Get the segments in order.
     *
     * @return list<Segment>
     */
    public function segments(): array
    {
        return $this->segments;
    }

    /**
     * Get the text without any markup.
     */
    public function plainText(): string
    {
        return implode('', array_map(fn (Segment $segment): string => $segment->text, $this->segments));
    }

    /**
     * Determine whether the text has no segments.
     */
    public function isEmpty(): bool
    {
        return $this->segments === [];
    }

    /**
     * Append a segment with the given style unless its text is empty.
     */
    public function segment(string $text, SegmentStyle $style): self
    {
        if ($text !== '') {
            $this->segments[] = new Segment($text, $style);
        }

        return $this;
    }
}
