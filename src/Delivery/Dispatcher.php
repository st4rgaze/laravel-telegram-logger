<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Delivery;

use Illuminate\Support\Facades\Bus;
use St4rgaze\TelegramLogger\Delivery\Jobs\SendTelegramDocument;
use St4rgaze\TelegramLogger\Delivery\Jobs\SendTelegramMessage;
use St4rgaze\TelegramLogger\Enums\Delivery;
use St4rgaze\TelegramLogger\Logging\ChannelConfig;
use St4rgaze\TelegramLogger\Telegram\TelegramApiException;
use St4rgaze\TelegramLogger\Telegram\TelegramClient;

/**
 * @internal
 */
final readonly class Dispatcher
{
    /**
     * Create a dispatcher.
     */
    public function __construct(
        private TelegramClient $client,
        private FailureReporter $failures,
    ) {}

    /**
     * Deliver the message with the channel's delivery mode.
     */
    public function dispatch(OutgoingMessage $message, ChannelConfig $config): void
    {
        if ($config->delivery === Delivery::Sync) {
            $this->send($message, $config);

            return;
        }

        $jobs = array_map(
            fn (string $chunk): SendTelegramMessage => new SendTelegramMessage($config->name, $chunk, $message->silent),
            $message->chunks,
        );

        if ($message->trace instanceof TraceFile) {
            $jobs[] = new SendTelegramDocument($config->name, $message->trace, $message->silent);
        }

        Bus::chain($jobs)
            ->onConnection($config->queueConnection)
            ->onQueue($config->queueName)
            ->dispatch();
    }

    /**
     * Send every part now, stopping at the first failure.
     */
    private function send(OutgoingMessage $message, ChannelConfig $config): void
    {
        try {
            foreach ($message->chunks as $chunk) {
                $this->client->sendMessage($config, $chunk, $message->silent);
            }

            if ($message->trace instanceof TraceFile) {
                $this->client->sendDocument($config, $message->trace, $message->silent);
            }
        } catch (TelegramApiException $telegramApiException) {
            $this->failures->report($config, $telegramApiException->getMessage());
        }
    }
}
