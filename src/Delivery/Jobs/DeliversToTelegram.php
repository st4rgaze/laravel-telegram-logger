<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Delivery\Jobs;

use Closure;
use Illuminate\Queue\Jobs\SyncJob;
use St4rgaze\TelegramLogger\Delivery\FailureReporter;
use St4rgaze\TelegramLogger\Logging\ChannelConfig;
use St4rgaze\TelegramLogger\Telegram\TelegramApiException;
use Throwable;

/**
 * Delivery policy shared by the jobs: retry by releasing, never throw, never end in failed().
 *
 * @internal
 */
trait DeliversToTelegram
{
    /**
     * Run a send for the job's channel, releasing for retries and deleting after a final failure.
     *
     * @param  Closure(ChannelConfig): void  $send
     */
    private function deliver(FailureReporter $failures, Closure $send): void
    {
        try {
            $config = ChannelConfig::forChannel($this->channel);
        } catch (Throwable $throwable) {
            $failures->report(null, $throwable->getMessage());
            $this->stop();

            return;
        }

        if (! $config->isConfigured()) {
            $failures->report($config, 'the channel has no bot token or chat id');
            $this->stop();

            return;
        }

        try {
            $send($config);
        } catch (TelegramApiException $exception) {
            if ($this->canRetry($exception)) {
                $this->release($this->retryDelay($exception));

                return;
            }

            $failures->report($config, $exception->getMessage());
            $this->stop();
        } catch (Throwable $exception) {
            $failures->report($config, $exception::class);
            $this->stop();
        }
    }

    /**
     * Determine whether the job can be released for another attempt; the sync queue never retries a release.
     */
    private function canRetry(TelegramApiException $exception): bool
    {
        return $exception->isRetryable()
            && $this->attempts() < $this->tries
            && ! $this->job instanceof SyncJob;
    }

    /**
     * Get how long to wait before the next attempt.
     */
    private function retryDelay(TelegramApiException $exception): int
    {
        if ($exception->retryAfter !== null) {
            return min($exception->retryAfter, 300);
        }

        return [10, 30, 60][min($this->attempts(), 3) - 1];
    }

    /**
     * Delete the job and drop the rest of its chain.
     */
    private function stop(): void
    {
        $this->chained = [];
        $this->delete();
    }
}
