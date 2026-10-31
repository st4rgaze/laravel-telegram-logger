<?php

declare(strict_types=1);

use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository;
use St4rgaze\TelegramLogger\Throttling\Throttle;

it('treats a repeat inside the dedupe window as a duplicate', function (): void {
    $throttle = app(Throttle::class);

    expect($throttle->isDuplicate('fp', channelConfig()))->toBeFalse()
        ->and($throttle->isDuplicate('fp', channelConfig()))->toBeTrue();

    $this->travel(61)->seconds();

    expect($throttle->isDuplicate('fp', channelConfig()))->toBeFalse();
});

it('limits telegram messages per chat per minute', function (): void {
    $throttle = app(Throttle::class);
    $this->travelTo(now()->startOfMinute());

    expect($throttle->reserve(channelConfig(), 18, 1))->toBeTrue()
        ->and($throttle->reserve(channelConfig(), 2, 1))->toBeTrue()
        ->and($throttle->reserve(channelConfig(), 1, 1))->toBeFalse()
        ->and($throttle->reserve(channelConfig(['chat_id' => '999']), 1, 1))->toBeTrue();

    $this->travel(1)->minutes();

    expect($throttle->reserve(channelConfig(), 1, 1))->toBeTrue();
});

it('lets the first message of a minute through even when it is larger than the limit', function (): void {
    expect(app(Throttle::class)->reserve(channelConfig(['throttle' => ['max_per_minute' => 2]]), 4, 1))->toBeTrue();
});

it('counts suppressed records until a message acknowledges them', function (): void {
    $throttle = app(Throttle::class);
    $this->travelTo(now()->startOfMinute());

    $throttle->isDuplicate('fp', channelConfig());
    $throttle->isDuplicate('fp', channelConfig());
    $throttle->reserve(channelConfig(), 20, 1);
    $throttle->reserve(channelConfig(), 1, 3);

    expect($throttle->pendingSuppressed(channelConfig()))->toBe(4);

    $this->travel(1)->minutes();
    $throttle->reserve(channelConfig(), 1, 1, acknowledged: 4);

    expect($throttle->pendingSuppressed(channelConfig()))->toBe(0);
});

it('uses the configured cache store', function (): void {
    config(['cache.stores.throttle' => ['driver' => 'array']]);

    app(Throttle::class)->isDuplicate('fp', channelConfig(['throttle' => ['cache_store' => 'throttle']]));

    expect(cache()->store('throttle')->has('telegram-logger:dedupe:fp'))->toBeTrue()
        ->and(cache()->has('telegram-logger:dedupe:fp'))->toBeFalse();
});

it('never throttles when disabled', function (): void {
    $throttle = app(Throttle::class);
    $config = channelConfig(['throttle' => ['enabled' => false, 'max_per_minute' => 1]]);

    expect($throttle->isDuplicate('fp', $config))->toBeFalse()
        ->and($throttle->isDuplicate('fp', $config))->toBeFalse()
        ->and($throttle->reserve($config, 5, 1))->toBeTrue()
        ->and($throttle->reserve($config, 5, 1))->toBeTrue()
        ->and($throttle->pendingSuppressed($config))->toBe(0);
});

it('fails open when the cache fails', function (): void {
    $broken = new class implements Factory
    {
        public function store($name = null): Repository
        {
            throw new RuntimeException('redis is down');
        }
    };
    $throttle = new Throttle($broken);

    expect($throttle->isDuplicate('fp', channelConfig()))->toBeFalse()
        ->and($throttle->reserve(channelConfig(), 1, 1))->toBeTrue()
        ->and($throttle->pendingSuppressed(channelConfig()))->toBe(0);
});

it('does not throttle with a null cache store', function (): void {
    config(['cache.stores.none' => ['driver' => 'null']]);
    $config = channelConfig(['throttle' => ['cache_store' => 'none']]);

    expect(app(Throttle::class)->isDuplicate('fp', $config))->toBeFalse()
        ->and(app(Throttle::class)->isDuplicate('fp', $config))->toBeFalse()
        ->and(app(Throttle::class)->reserve($config, 1, 1))->toBeTrue();
});
