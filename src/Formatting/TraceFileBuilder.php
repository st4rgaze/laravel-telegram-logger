<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Formatting;

use St4rgaze\TelegramLogger\Delivery\TraceFile;
use St4rgaze\TelegramLogger\Logging\ChannelConfig;
use St4rgaze\TelegramLogger\Logging\LogEntry;

/**
 * @internal
 */
final readonly class TraceFileBuilder
{
    /**
     * Build the text attachment holding stack traces and overflow text, or null when there is nothing to attach.
     *
     * @param  list<LogEntry>  $entries
     */
    public function build(array $entries, string $overflow, ChannelConfig $config, string $fingerprint, string $caption): ?TraceFile
    {
        $sections = [];

        if ($config->attachTrace) {
            foreach ($entries as $entry) {
                if ($entry->exceptions !== []) {
                    $sections[] = $this->describe($entry);
                }
            }
        }

        if ($overflow !== '') {
            $sections[] = "== Message continued ==\n".$overflow;
        }

        if ($sections === []) {
            return null;
        }

        return new TraceFile(
            sprintf('trace-%s-%s.txt', mb_substr($fingerprint, 0, 8), $entries[0]->datetime->format('YmdHis')),
            $this->truncate(implode("\n\n", $sections), $config->traceMaxBytes),
            mb_substr($caption, 0, 1024),
        );
    }

    /**
     * Describe one entry's exception chain, request and context.
     */
    private function describe(LogEntry $entry): string
    {
        $lines = [sprintf('== %s %s ==', $entry->level->getName(), $entry->datetime->format('Y-m-d H:i:s T'))];

        foreach ($entry->exceptions as $index => $exception) {
            $lines[] = ($index === 0 ? '' : "\nCaused by ").$exception->class.': '.$exception->message;
            $lines[] = 'at '.$exception->file.':'.$exception->line;
            $lines[] = $exception->trace;
        }

        if ($entry->request !== []) {
            $lines[] = "\nRequest:";

            foreach ($entry->request as $key => $value) {
                $lines[] = $key.': '.$value;
            }
        }

        if ($entry->context !== []) {
            $lines[] = "\nContext:";
            $lines[] = (string) json_encode($entry->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }

        return implode("\n", $lines);
    }

    /**
     * Cut the contents to the byte limit at a character boundary.
     */
    private function truncate(string $contents, int $maxBytes): string
    {
        if (mb_strlen($contents, '8bit') <= $maxBytes) {
            return $contents;
        }

        return mb_strcut($contents, 0, $maxBytes, 'UTF-8').sprintf("\n[truncated at %d bytes]", $maxBytes);
    }
}
