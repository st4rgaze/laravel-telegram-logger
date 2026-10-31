<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Logging;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Monolog\Logger;
use St4rgaze\TelegramLogger\Enums\Delivery;
use St4rgaze\TelegramLogger\Exceptions\InvalidConfiguration;

/**
 * @internal
 */
final readonly class CreateTelegramLogger
{
    /**
     * The name used for channels built on demand without a name.
     */
    private const string ON_DEMAND = 'on-demand';

    /**
     * Create the driver factory.
     */
    public function __construct(private Application $app) {}

    /**
     * Create the Monolog logger for a "telegram" log channel.
     *
     * @param  array<array-key, mixed>  $config
     *
     * @throws InvalidConfiguration
     */
    public function __invoke(array $config): Logger
    {
        $configured = $this->configuredName($config);
        $channelConfig = ChannelConfig::from(Config::array('telegram-logger', []), $config, $configured ?? $this->label($config));

        if ($configured === null && $channelConfig->delivery === Delivery::Queue) {
            throw InvalidConfiguration::unnamedQueuedChannel();
        }

        return new Logger($channelConfig->name, [
            $this->app->make(TelegramHandler::class, ['config' => $channelConfig]),
        ]);
    }

    /**
     * Find the logging channel whose config is identical, which is what a queued job can rebuild later.
     *
     * @param  array<array-key, mixed>  $config
     */
    private function configuredName(array $config): ?string
    {
        foreach (Config::array('logging.channels', []) as $name => $channel) {
            if (is_string($name) && $channel === $config) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Get a display name for an on-demand channel.
     *
     * @param  array<array-key, mixed>  $config
     */
    private function label(array $config): string
    {
        $name = $config['name'] ?? null;

        return is_string($name) && $name !== '' ? $name : self::ON_DEMAND;
    }
}
