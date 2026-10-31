<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Delivery;

use Illuminate\Log\LogManager;
use St4rgaze\TelegramLogger\Logging\ChannelConfig;
use St4rgaze\TelegramLogger\Logging\ReentrancyGuard;
use St4rgaze\TelegramLogger\Telegram\SecretMasker;
use Throwable;

/**
 * @internal
 */
final readonly class FailureReporter
{
    /**
     * Create a reporter for delivery failures.
     */
    public function __construct(private LogManager $log) {}

    /**
     * Write a one-line, token-free note about a failed delivery to the fallback channel or error_log().
     */
    public function report(?ChannelConfig $config, string $reason): void
    {
        $line = $config instanceof ChannelConfig
            ? '[telegram-logger] Delivery to chat *** failed: '.SecretMasker::mask($reason, $config->token)
            : '[telegram-logger] Delivery failed: '.$reason;

        ReentrancyGuard::run(function () use ($config, $line): void {
            $fallback = $config?->fallbackChannel;

            if ($fallback === null || $fallback === $config->name) {
                error_log($line);

                return;
            }

            try {
                $this->log->channel($fallback)->warning($line);
            } catch (Throwable) {
                error_log($line);
            }
        });
    }
}
