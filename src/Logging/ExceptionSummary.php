<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Logging;

use St4rgaze\TelegramLogger\Redaction\ExceptionMessage;
use Throwable;

final readonly class ExceptionSummary
{
    /**
     * Create a summary of one exception in a chain.
     */
    public function __construct(
        public string $class,
        public string $message,
        public string $file,
        public int $line,
        public string $trace,
    ) {}

    /**
     * Summarise an exception and its previous exceptions, outermost first.
     *
     * @return list<self>
     */
    public static function chain(Throwable $exception, string $basePath): array
    {
        $summaries = [];
        $prefix = mb_rtrim($basePath, '/').'/';

        for ($current = $exception; $current instanceof Throwable; $current = $current->getPrevious()) {
            $file = $current->getFile();

            $summaries[] = new self(
                $current::class,
                ExceptionMessage::of($current),
                str_starts_with($file, $prefix) ? mb_substr($file, mb_strlen($prefix)) : $file,
                $current->getLine(),
                self::trace($current, $prefix),
            );
        }

        return $summaries;
    }

    /**
     * Describe the stack frames with file, line and function only, never the call arguments.
     */
    private static function trace(Throwable $exception, string $prefix): string
    {
        $lines = [];

        foreach ($exception->getTrace() as $index => $frame) {
            $location = isset($frame['file'])
                ? sprintf('%s(%d)', str_starts_with($frame['file'], $prefix) ? mb_substr($frame['file'], mb_strlen($prefix)) : $frame['file'], $frame['line'] ?? 0)
                : '[internal function]';

            $lines[] = sprintf('#%d %s: %s%s%s()', $index, $location, $frame['class'] ?? '', $frame['type'] ?? '', $frame['function']);
        }

        $lines[] = sprintf('#%d {main}', count($lines));

        return implode("\n", $lines);
    }
}
