<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Delivery\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use St4rgaze\TelegramLogger\Delivery\FailureReporter;
use St4rgaze\TelegramLogger\Logging\ChannelConfig;
use St4rgaze\TelegramLogger\Telegram\TelegramClient;

/**
 * @internal
 */
final class SendTelegramMessage implements ShouldQueue
{
    use DeliversToTelegram;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a job, dispatched immediately even inside a database transaction, that sends one HTML chunk.
     */
    public function __construct(
        public readonly string $channel,
        public readonly string $html,
        public readonly bool $silent,
    ) {
        $this->afterCommit = false;
    }

    /**
     * Send the chunk.
     */
    public function handle(TelegramClient $client, FailureReporter $failures): void
    {
        $this->deliver($failures, function (ChannelConfig $config) use ($client): void {
            $client->sendMessage($config, $this->html, $this->silent);
        });
    }
}
