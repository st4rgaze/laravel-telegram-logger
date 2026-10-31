<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Logging;

use Illuminate\Support\Facades\Config;
use Monolog\Level;
use SensitiveParameter;
use St4rgaze\TelegramLogger\Enums\Delivery;
use St4rgaze\TelegramLogger\Exceptions\InvalidConfiguration;
use St4rgaze\TelegramLogger\Formatting\MessageFormatter;

/**
 * @internal
 */
final readonly class ChannelConfig
{
    /**
     * Create a validated channel configuration.
     *
     * @param  list<string>  $redactKeys
     * @param  list<string>  $redactPatterns
     * @param  array{url: bool, method: bool, route: bool, user_id: bool, ip: bool}  $requestContext
     * @param  class-string<MessageFormatter>  $formatter
     */
    public function __construct(
        public string $name,
        #[SensitiveParameter] public ?string $token,
        public ?string $chatId,
        public ?int $messageThreadId,
        public string $apiHost,
        public ?string $proxy,
        public float $timeout,
        public Delivery $delivery,
        public ?string $queueConnection,
        public ?string $queueName,
        public bool $throttleEnabled,
        public int $dedupeSeconds,
        public int $maxPerMinute,
        public ?string $cacheStore,
        public array $redactKeys,
        public array $redactPatterns,
        public string $redactReplacement,
        public array $requestContext,
        public bool $attachTrace,
        public int $traceMaxBytes,
        public int $maxChunks,
        public Level $silentBelow,
        public string $formatter,
        public ?string $fallbackChannel,
        public Level $level,
    ) {}

    /**
     * Get the properties shown by var_dump() and print_r(), with the token masked.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        /** @var array<string, mixed> $properties */
        $properties = get_object_vars($this);
        $properties['token'] = $this->token === null ? null : '***';

        return $properties;
    }

    /**
     * Build a configuration from the global package config and a logging channel's config.
     *
     * @param  array<array-key, mixed>  $global
     * @param  array<array-key, mixed>  $channel
     *
     * @throws InvalidConfiguration
     */
    public static function from(array $global, array $channel, string $name): self
    {
        $config = new ConfigReader(ConfigReader::merge($global, $channel));

        return new self(
            name: $name,
            token: $config->nullableString('token'),
            chatId: $config->nullableString('chat_id'),
            messageThreadId: $config->nullablePositiveInt('message_thread_id'),
            apiHost: self::apiHost($config->string('api_host')),
            proxy: $config->nullableString('proxy'),
            timeout: $config->positiveFloat('timeout'),
            delivery: Delivery::tryFrom($config->string('delivery'))
                ?? throw InvalidConfiguration::invalidValue('delivery', '"queue" or "sync"'),
            queueConnection: $config->nullableString('queue.connection'),
            queueName: $config->nullableString('queue.name'),
            throttleEnabled: $config->bool('throttle.enabled'),
            dedupeSeconds: $config->positiveInt('throttle.dedupe_seconds'),
            maxPerMinute: $config->positiveInt('throttle.max_per_minute'),
            cacheStore: $config->nullableString('throttle.cache_store'),
            redactKeys: $config->stringList('redact.keys'),
            redactPatterns: $config->patternList('redact.patterns'),
            redactReplacement: $config->string('redact.replacement'),
            requestContext: [
                'url' => $config->bool('request_context.url'),
                'method' => $config->bool('request_context.method'),
                'route' => $config->bool('request_context.route'),
                'user_id' => $config->bool('request_context.user_id'),
                'ip' => $config->bool('request_context.ip'),
            ],
            attachTrace: $config->bool('trace.attach'),
            traceMaxBytes: $config->positiveInt('trace.max_bytes'),
            maxChunks: $config->positiveInt('max_chunks'),
            silentBelow: $config->level('silent_below', Level::Error),
            formatter: self::formatter($config->string('formatter')),
            fallbackChannel: $config->nullableString('fallback_channel'),
            level: $config->level('level', Level::Debug),
        );
    }

    /**
     * Build the configuration of a named logging channel from the application config.
     *
     * @throws InvalidConfiguration
     */
    public static function forChannel(string $name): self
    {
        return self::from(
            Config::array('telegram-logger', []),
            Config::array("logging.channels.{$name}", []),
            $name,
        );
    }

    /**
     * Determine whether a token and chat id are present.
     */
    public function isConfigured(): bool
    {
        return $this->token !== null && $this->chatId !== null;
    }

    /**
     * Get a stable key identifying the destination chat and topic.
     */
    public function chatKey(): string
    {
        return sha1($this->chatId.'|'.$this->messageThreadId);
    }

    /**
     * Validate that the formatter class implements the formatter contract.
     *
     * @return class-string<MessageFormatter>
     */
    private static function formatter(string $class): string
    {
        if (! is_a($class, MessageFormatter::class, true)) {
            throw InvalidConfiguration::invalidValue('formatter', 'a class implementing '.MessageFormatter::class);
        }

        return $class;
    }

    /**
     * Validate the API host scheme and strip a trailing slash.
     */
    private static function apiHost(string $host): string
    {
        $scheme = parse_url($host, PHP_URL_SCHEME);
        $hostname = parse_url($host, PHP_URL_HOST);

        $isSecure = $scheme === 'https';
        $isLocal = $scheme === 'http' && in_array($hostname, ['localhost', '127.0.0.1'], true);

        if (! $isSecure && ! $isLocal) {
            throw InvalidConfiguration::insecureApiHost();
        }

        return mb_rtrim($host, '/');
    }
}
