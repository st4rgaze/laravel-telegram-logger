<?php

declare(strict_types=1);

use Monolog\Level;
use St4rgaze\TelegramLogger\Enums\Delivery;
use St4rgaze\TelegramLogger\Exceptions\InvalidConfiguration;
use St4rgaze\TelegramLogger\Logging\ChannelConfig;

it('builds typed values from the package defaults', function (): void {
    $config = channelConfig();

    expect($config->name)->toBe('telegram')
        ->and($config->token)->toBe(TEST_TOKEN)
        ->and($config->chatId)->toBe(TEST_CHAT_ID)
        ->and($config->messageThreadId)->toBeNull()
        ->and($config->apiHost)->toBe('https://api.telegram.org')
        ->and($config->proxy)->toBeNull()
        ->and($config->timeout)->toBe(5.0)
        ->and($config->delivery)->toBe(Delivery::Queue)
        ->and($config->queueConnection)->toBeNull()
        ->and($config->queueName)->toBeNull()
        ->and($config->throttleEnabled)->toBeTrue()
        ->and($config->dedupeSeconds)->toBe(60)
        ->and($config->maxPerMinute)->toBe(20)
        ->and($config->cacheStore)->toBeNull()
        ->and($config->redactKeys)->toContain('password', 'authorization')
        ->and($config->redactReplacement)->toBe('[redacted]')
        ->and($config->requestContext)->toBe(['url' => true, 'method' => true, 'route' => true, 'user_id' => true, 'ip' => false])
        ->and($config->attachTrace)->toBeTrue()
        ->and($config->traceMaxBytes)->toBe(200_000)
        ->and($config->maxChunks)->toBe(3)
        ->and($config->silentBelow)->toBe(Level::Error)
        ->and($config->fallbackChannel)->toBeNull()
        ->and($config->level)->toBe(Level::Debug);
});

it('lets channel values override global ones', function (): void {
    expect(channelConfig(['chat_id' => '42', 'level' => 'error', 'delivery' => 'sync']))
        ->chatId->toBe('42')
        ->level->toBe(Level::Error)
        ->delivery->toBe(Delivery::Sync);
});

it('merges nested associative arrays per key', function (): void {
    $config = channelConfig(['throttle' => ['dedupe_seconds' => 300]]);

    expect($config->dedupeSeconds)->toBe(300)
        ->and($config->maxPerMinute)->toBe(20);
});

it('replaces list arrays', function (): void {
    expect(channelConfig(['redact' => ['keys' => ['pin']]])->redactKeys)->toBe(['pin']);
});

it('accepts numeric strings from env values', function (): void {
    $config = channelConfig(['timeout' => '2.5', 'message_thread_id' => '77', 'max_chunks' => '5']);

    expect($config->timeout)->toBe(2.5)
        ->and($config->messageThreadId)->toBe(77)
        ->and($config->maxChunks)->toBe(5);
});

it('normalises an integer chat id to string', function (): void {
    expect(channelConfig(['chat_id' => -1001234567890])->chatId)->toBe('-1001234567890');
});

it('treats empty optional strings as null', function (): void {
    $config = channelConfig(['message_thread_id' => '', 'proxy' => '', 'fallback_channel' => '']);

    expect($config->messageThreadId)->toBeNull()
        ->and($config->proxy)->toBeNull()
        ->and($config->fallbackChannel)->toBeNull();
});

it('trims a trailing slash from the api host', function (): void {
    expect(channelConfig(['api_host' => 'https://bot.example.com/'])->apiHost)->toBe('https://bot.example.com');
});

it('is configured only with both token and chat id', function (array $override, bool $configured): void {
    expect(channelConfig($override)->isConfigured())->toBe($configured);
})->with([
    'both' => [[], true],
    'no token' => [['token' => null], false],
    'blank token' => [['token' => '  '], false],
    'no chat' => [['chat_id' => null], false],
]);

it('rejects invalid values', function (array $override): void {
    expect(fn (): ChannelConfig => channelConfig($override))->toThrow(InvalidConfiguration::class);
})->with([
    'delivery' => [['delivery' => 'fast']],
    'timeout' => [['timeout' => 'abc']],
    'zero timeout' => [['timeout' => 0]],
    'max_chunks' => [['max_chunks' => 0]],
    'dedupe' => [['throttle' => ['dedupe_seconds' => -1]]],
    'enabled' => [['throttle' => ['enabled' => 'yes please']]],
    'silent_below' => [['silent_below' => 'loud']],
    'level' => [['level' => 'loud']],
    'formatter' => [['formatter' => '']],
    'redact keys' => [['redact' => ['keys' => [1, 2]]]],
    'request context' => [['request_context' => ['ip' => 'maybe']]],
    'chat id array' => [['chat_id' => ['x']]],
    'http host' => [['api_host' => 'http://example.com']],
    'no scheme' => [['api_host' => 'api.telegram.org']],
]);

it('allows http for a local bot api server', function (string $host): void {
    expect(channelConfig(['api_host' => $host])->apiHost)->toBe($host);
})->with(['http://localhost:8081', 'http://127.0.0.1:8081']);

it('derives a stable chat key from chat and thread', function (): void {
    expect(channelConfig()->chatKey())->toBe(channelConfig()->chatKey())
        ->and(channelConfig(['message_thread_id' => 7])->chatKey())->not->toBe(channelConfig()->chatKey());
});

it('hides the token from debug output', function (): void {
    expect(print_r(channelConfig(), true))->not->toContain('ABCDEFGHIJKLMNOPQRSTUVWXYZ')
        ->and(var_export(channelConfig()->__debugInfo(), true))->not->toContain('ABCDEFGHIJKLMNOPQRSTUVWXYZ');
});

it('resolves a named logging channel from config', function (): void {
    config([
        'telegram-logger.token' => TEST_TOKEN,
        'telegram-logger.chat_id' => TEST_CHAT_ID,
        'logging.channels.ops' => ['driver' => 'telegram', 'chat_id' => '99'],
    ]);

    expect(ChannelConfig::forChannel('ops'))
        ->name->toBe('ops')
        ->chatId->toBe('99')
        ->token->toBe(TEST_TOKEN);
});
