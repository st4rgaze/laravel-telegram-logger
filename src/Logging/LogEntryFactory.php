<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Logging;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\LogRecord;
use St4rgaze\TelegramLogger\Context\RequestContextResolver;
use St4rgaze\TelegramLogger\Redaction\ExceptionMessage;
use St4rgaze\TelegramLogger\Redaction\Redactor;
use Throwable;

/**
 * @internal
 */
final readonly class LogEntryFactory
{
    /**
     * Create a factory for log entries.
     */
    public function __construct(
        private RequestContextResolver $request,
        private Application $app,
    ) {}

    /**
     * Turn a Monolog record into a redacted, normalised log entry.
     */
    public function make(LogRecord $record, ChannelConfig $config, int $suppressedCount): LogEntry
    {
        $redactor = Redactor::fromConfig($config);
        $context = $record->context;
        $message = $record->message;
        $exceptions = [];

        $exception = $context['exception'] ?? null;

        if ($exception instanceof Throwable) {
            unset($context['exception']);
            $message = $this->describeExceptions($message, $exception);
            $exceptions = array_map(fn (ExceptionSummary $summary): ExceptionSummary => new ExceptionSummary(
                $summary->class,
                $redactor->redactText($summary->message),
                $summary->file,
                $summary->line,
                $redactor->redactText($summary->trace),
            ), ExceptionSummary::chain($exception, $this->app->basePath()));
        }

        return new LogEntry(
            level: $record->level,
            channel: $record->channel,
            message: $redactor->redactText($message),
            context: $redactor->redact($this->normalize($context)),
            extra: $redactor->redact($this->normalize($record->extra)),
            datetime: DateTimeImmutable::createFromInterface($record->datetime)
                ->setTimezone(new DateTimeZone(Config::string('app.timezone', 'UTC'))),
            exceptions: $exceptions,
            request: array_map($redactor->redactText(...), $this->request->resolve($config)),
            suppressedCount: $suppressedCount,
            appName: Config::string('app.name', 'Laravel'),
            environment: $this->app->environment(),
        );
    }

    /**
     * Replace raw exception messages inside the record message with their safe descriptions.
     */
    private function describeExceptions(string $message, Throwable $exception): string
    {
        for ($current = $exception; $current instanceof Throwable; $current = $current->getPrevious()) {
            $raw = $current->getMessage();

            if ($raw !== '') {
                $message = str_replace($raw, ExceptionMessage::of($current), $message);
            }
        }

        return $message;
    }

    /**
     * Normalise values into plain arrays and scalars.
     *
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function normalize(array $values): array
    {
        $normalized = (new NormalizerFormatter)->normalizeValue($values);

        return is_array($normalized) ? $normalized : [];
    }
}
