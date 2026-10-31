<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Formatting;

use Monolog\Level;
use St4rgaze\TelegramLogger\Logging\LogEntry;

final readonly class DefaultMessageFormatter implements MessageFormatter
{
    /**
     * Build the default message layout: header, message, exception, request, context, footer.
     */
    public function format(LogEntry $entry): TelegramText
    {
        $text = TelegramText::make()
            ->bold($this->icon($entry->level).' '.$entry->level->getName())
            ->text(' · '.$entry->appName.' · '.$entry->environment)
            ->line()
            ->text($entry->message)
            ->line(2);

        $exception = $entry->exceptions[0] ?? null;

        if ($exception !== null) {
            $name = class_basename($exception->class);

            $text->code($exception->message === $entry->message ? $name : $name.': '.$exception->message)
                ->line()
                ->code($exception->file.':'.$exception->line)
                ->line(2);
        }

        if ($entry->request !== []) {
            $text->text('🌐 '.$this->requestLine($entry->request))->line(2);
        }

        foreach ([$entry->context, $entry->extra] as $values) {
            if ($values !== []) {
                $text->pre($this->json($values))->line(2);
            }
        }

        $footer = '🕒 '.$entry->datetime->format('Y-m-d H:i:s T');

        if ($entry->suppressedCount > 0) {
            $footer .= sprintf(' · ➕ %d similar suppressed', $entry->suppressedCount);
        }

        return $text->text($footer);
    }

    /**
     * Get the icon for a level.
     */
    private function icon(Level $level): string
    {
        return match ($level) {
            Level::Debug => '🔍',
            Level::Info => 'ℹ️',
            Level::Notice => '📝',
            Level::Warning => '⚠️',
            Level::Error => '🔴',
            Level::Critical => '🚨',
            Level::Alert => '🆘',
            Level::Emergency => '💥',
        };
    }

    /**
     * Join the request details into one line.
     *
     * @param  array<string, string>  $request
     */
    private function requestLine(array $request): string
    {
        $parts = [
            mb_trim(($request['method'] ?? '').' '.($request['url'] ?? '')),
            $request['route'] ?? '',
            isset($request['user_id']) ? 'user '.$request['user_id'] : '',
            isset($request['ip']) ? 'ip '.$request['ip'] : '',
        ];

        return implode(' · ', array_filter($parts, fn (string $part): bool => $part !== ''));
    }

    /**
     * Encode values as pretty JSON.
     *
     * @param  array<array-key, mixed>  $values
     */
    private function json(array $values): string
    {
        $json = json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return $json === false ? '{}' : $json;
    }
}
