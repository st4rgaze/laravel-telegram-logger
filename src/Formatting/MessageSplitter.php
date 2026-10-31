<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Formatting;

/**
 * @internal
 */
final readonly class MessageSplitter
{
    public const string OVERFLOW_NOTE = "\n…continued in the attached file";

    /**
     * Split a text into HTML chunks that each fit Telegram's limit in visible characters.
     */
    public function split(TelegramText $text, int $maxChunks, int $limit = 4096): SplitResult
    {
        $segments = array_map(
            fn (Segment $segment): Segment => new Segment(mb_scrub($segment->text, 'UTF-8'), $segment->style),
            $text->segments(),
        );

        if ($this->length($segments) <= $limit) {
            return new SplitResult([$this->render($segments)], '');
        }

        $prefixReserve = mb_strlen(sprintf('(%d/%d) ', $maxChunks, $maxChunks));
        $budget = max(1, $limit - $prefixReserve - mb_strlen(self::OVERFLOW_NOTE));

        $pieces = $this->pack($segments, $budget);
        $kept = array_slice($pieces, 0, $maxChunks);
        $dropped = array_slice($pieces, $maxChunks);
        $total = count($kept);

        $chunks = [];

        foreach ($kept as $index => $piece) {
            $chunk = sprintf('(%d/%d) ', $index + 1, $total).$this->render($piece);

            if ($dropped !== [] && $index === $total - 1) {
                $chunk .= $this->escape(self::OVERFLOW_NOTE);
            }

            $chunks[] = $chunk;
        }

        $overflow = implode('', array_map($this->plain(...), $dropped));

        return new SplitResult($chunks, $overflow);
    }

    /**
     * Pack segments into pieces of at most the budget in characters, cutting segments where needed.
     *
     * @param  list<Segment>  $segments
     * @return list<list<Segment>>
     */
    private function pack(array $segments, int $budget): array
    {
        $pieces = [];
        $current = [];
        $used = 0;

        while ($segments !== []) {
            $segment = array_shift($segments);
            $length = mb_strlen($segment->text);
            $room = $budget - $used;

            if ($length <= $room) {
                $current[] = $segment;
                $used += $length;

                continue;
            }

            $cut = $this->cutPosition($segment->text, $room);

            if ($cut === 0 && $current !== []) {
                $pieces[] = $current;
                $current = [];
                $used = 0;
                array_unshift($segments, $segment);

                continue;
            }

            $cut = $cut === 0 ? $room : $cut;

            $current[] = new Segment(mb_substr($segment->text, 0, $cut), $segment->style);
            $pieces[] = $current;
            $current = [];
            $used = 0;
            array_unshift($segments, new Segment(mb_substr($segment->text, $cut), $segment->style));
        }

        if ($current !== []) {
            $pieces[] = $current;
        }

        return $pieces;
    }

    /**
     * Find where to cut a text within the room: after the last newline, else after the last space, else 0.
     */
    private function cutPosition(string $text, int $room): int
    {
        if ($room < 1) {
            return 0;
        }

        $slice = mb_substr($text, 0, $room);

        foreach (["\n", ' '] as $delimiter) {
            $position = mb_strrpos($slice, $delimiter);

            if ($position !== false) {
                return $position + 1;
            }
        }

        return 0;
    }

    /**
     * Render segments as Telegram HTML.
     *
     * @param  list<Segment>  $segments
     */
    private function render(array $segments): string
    {
        $html = '';

        foreach ($segments as $segment) {
            if ($segment->text !== '') {
                $html .= $segment->style->open().$this->escape($segment->text).$segment->style->close();
            }
        }

        return $html;
    }

    /**
     * Get the plain text of segments.
     *
     * @param  list<Segment>  $segments
     */
    private function plain(array $segments): string
    {
        return implode('', array_map(fn (Segment $segment): string => $segment->text, $segments));
    }

    /**
     * Get the total length of segments in characters.
     *
     * @param  list<Segment>  $segments
     */
    private function length(array $segments): int
    {
        return mb_strlen($this->plain($segments));
    }

    /**
     * Escape the characters Telegram's HTML parse mode reserves.
     */
    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
