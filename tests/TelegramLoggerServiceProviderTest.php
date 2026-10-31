<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use St4rgaze\TelegramLogger\Telegram\TelegramClient;
use St4rgaze\TelegramLogger\TelegramLoggerServiceProvider;

it('merges the package config', function (): void {
    expect(config('telegram-logger.delivery'))->toBe('queue')
        ->and(config('telegram-logger.throttle.max_per_minute'))->toBe(20)
        ->and(config('telegram-logger.api_host'))->toBe('https://api.telegram.org');
});

it('publishes the config under its tag', function (): void {
    expect(ServiceProvider::pathsToPublish(TelegramLoggerServiceProvider::class, 'telegram-logger-config'))
        ->toHaveCount(1);
});

it('binds the telegram client as a singleton on its own event-less http factory', function (): void {
    $client = app(TelegramClient::class);
    $http = (fn (): Illuminate\Http\Client\Factory => $this->http)->call($client);

    expect(app(TelegramClient::class))->toBe($client)
        ->and($http)->not->toBe(app(Illuminate\Http\Client\Factory::class))
        ->and($http->getDispatcher())->toBeNull();
});
