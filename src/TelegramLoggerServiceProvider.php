<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Log\LogManager;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Monolog\Logger;
use St4rgaze\TelegramLogger\Console\TestCommand;
use St4rgaze\TelegramLogger\Logging\CreateTelegramLogger;
use St4rgaze\TelegramLogger\Telegram\TelegramClient;

/**
 * @internal
 */
final class TelegramLoggerServiceProvider extends BaseServiceProvider
{
    /**
     * Register the package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/telegram-logger.php', 'telegram-logger');

        $this->app->singleton(TelegramClient::class, fn (): TelegramClient => new TelegramClient(new Factory));
    }

    /**
     * Bootstrap the package services.
     */
    public function boot(): void
    {
        $this->app->make(LogManager::class)->extend('telegram', fn (Application $app, array $config): Logger => $app->make(CreateTelegramLogger::class)($config));

        if ($this->app->runningInConsole()) {
            $this->commands([TestCommand::class]);

            $this->publishes([
                __DIR__.'/../config/telegram-logger.php' => $this->app->configPath('telegram-logger.php'),
            ], 'telegram-logger-config');
        }
    }
}
