<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Logging;

use Closure;
use Monolog\Handler\AbstractHandler;
use Monolog\Handler\ProcessableHandlerInterface;
use Monolog\Handler\ProcessableHandlerTrait;
use Monolog\LogRecord;
use St4rgaze\TelegramLogger\Delivery\Dispatcher;
use St4rgaze\TelegramLogger\Delivery\FailureReporter;
use St4rgaze\TelegramLogger\Delivery\TraceFile;
use St4rgaze\TelegramLogger\Formatting\MessageComposer;
use St4rgaze\TelegramLogger\Throttling\Fingerprint;
use St4rgaze\TelegramLogger\Throttling\Throttle;
use Throwable;

/**
 * A Monolog handler that never throws and never runs a Monolog formatter.
 *
 * @internal
 */
final class TelegramHandler extends AbstractHandler implements ProcessableHandlerInterface
{
    use ProcessableHandlerTrait;

    /**
     * Create the handler for one channel.
     */
    public function __construct(
        private readonly ChannelConfig $config,
        private readonly Throttle $throttle,
        private readonly LogEntryFactory $entries,
        private readonly MessageComposer $composer,
        private readonly Dispatcher $dispatcher,
        private readonly FailureReporter $failures,
    ) {
        parent::__construct($config->level);
    }

    /**
     * Send a single record.
     */
    public function handle(LogRecord $record): bool
    {
        if (! $this->isHandling($record)) {
            return false;
        }

        $this->send(fn (): array => [$this->processRecord($record)]);

        return ! $this->bubble;
    }

    /**
     * Send a batch of records as one message.
     *
     * @param  array<LogRecord>  $records
     */
    public function handleBatch(array $records): void
    {
        $this->send(function () use ($records): array {
            $handled = [];

            foreach ($records as $record) {
                if ($this->isHandling($record)) {
                    $handled[] = $this->processRecord($record);
                }
            }

            return $handled;
        });
    }

    /**
     * Process, deduplicate, compose, rate limit and dispatch records; never throws.
     *
     * @param  Closure(): list<LogRecord>  $records
     */
    private function send(Closure $records): void
    {
        if (! $this->config->isConfigured() || ReentrancyGuard::active()) {
            return;
        }

        try {
            ReentrancyGuard::run(function () use ($records): void {
                $this->deliver($records());
            });
        } catch (Throwable $throwable) {
            $this->failures->report($this->config, $throwable::class);
        }
    }

    /**
     * Deliver the records that are not duplicates, if the chat has room for them this minute.
     *
     * @param  list<LogRecord>  $records
     */
    private function deliver(array $records): void
    {
        $fresh = [];
        $fingerprint = null;

        foreach ($records as $record) {
            $recordFingerprint = Fingerprint::for($record, $this->config->name);

            if (! $this->throttle->isDuplicate($recordFingerprint, $this->config)) {
                $fingerprint ??= $recordFingerprint;
                $fresh[] = $record;
            }
        }

        if ($fresh === [] || $fingerprint === null) {
            return;
        }

        $suppressed = $this->throttle->pendingSuppressed($this->config);
        $entries = [];

        foreach ($fresh as $index => $record) {
            $entries[] = $this->entries->make($record, $this->config, $index === 0 ? $suppressed : 0);
        }

        $message = $this->composer->compose($entries, $this->config, $fingerprint);
        $messages = count($message->chunks) + ($message->trace instanceof TraceFile ? 1 : 0);

        if ($this->throttle->reserve($this->config, $messages, count($fresh), $suppressed)) {
            $this->dispatcher->dispatch($message, $this->config);
        }
    }
}
