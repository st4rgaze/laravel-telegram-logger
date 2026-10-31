<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Throttling;

use Illuminate\Cache\NullStore;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository;
use St4rgaze\TelegramLogger\Logging\ChannelConfig;
use Throwable;

/**
 * Deduplicates records and limits the Telegram messages sent per chat per minute; fails open.
 *
 * @internal
 */
final readonly class Throttle
{
    /**
     * Create a throttle backed by the application cache.
     */
    public function __construct(private Factory $cache) {}

    /**
     * Determine whether an identical record was seen inside the dedupe window, counting it as suppressed.
     */
    public function isDuplicate(string $fingerprint, ChannelConfig $config): bool
    {
        return $this->attempt($config, false, function (Repository $store) use ($fingerprint, $config): bool {
            if ($store->add('telegram-logger:dedupe:'.$fingerprint, 1, $config->dedupeSeconds)) {
                return false;
            }

            $this->countSuppressed($store, $config, 1);

            return true;
        });
    }

    /**
     * Get how many records were suppressed since the last message that got through.
     */
    public function pendingSuppressed(ChannelConfig $config): int
    {
        return $this->attempt($config, 0, function (Repository $store) use ($config): int {
            $count = $store->get($this->suppressedKey($config));

            return is_numeric($count) ? (int) $count : 0;
        });
    }

    /**
     * Reserve room for a number of Telegram messages in the chat's current minute.
     *
     * When there is no room the records are counted as suppressed; when there is, the suppressed
     * records the message reports are acknowledged. The first reservation of a minute always fits.
     */
    public function reserve(ChannelConfig $config, int $messages, int $records, int $acknowledged = 0): bool
    {
        return $this->attempt($config, true, function (Repository $store) use ($config, $messages, $records, $acknowledged): bool {
            $key = sprintf('telegram-logger:rate:%s:%d', $config->chatKey(), intdiv(now()->getTimestamp(), 60));

            $store->add($key, 0, 120);
            $used = $store->increment($key, $messages);

            if (is_int($used) && $used > $config->maxPerMinute && $used > $messages) {
                $store->decrement($key, $messages);
                $this->countSuppressed($store, $config, $records);

                return false;
            }

            if ($acknowledged > 0) {
                $store->decrement($this->suppressedKey($config), $acknowledged);
            }

            return true;
        });
    }

    /**
     * Run a cache operation, returning the fallback when throttling is off, the store is a null store, or the cache fails.
     *
     * @template TResult
     *
     * @param  TResult  $fallback
     * @param  callable(Repository): TResult  $operation
     * @return TResult
     */
    private function attempt(ChannelConfig $config, mixed $fallback, callable $operation): mixed
    {
        if (! $config->throttleEnabled) {
            return $fallback;
        }

        try {
            $store = $this->cache->store($config->cacheStore);

            if ($store->getStore() instanceof NullStore) {
                return $fallback;
            }

            return $operation($store);
        } catch (Throwable) {
            return $fallback;
        }
    }

    /**
     * Count suppressed records for the chat.
     */
    private function countSuppressed(Repository $store, ChannelConfig $config, int $records): void
    {
        $store->add($this->suppressedKey($config), 0, 86_400);
        $store->increment($this->suppressedKey($config), $records);
    }

    /**
     * Get the cache key of the chat's suppressed counter.
     */
    private function suppressedKey(ChannelConfig $config): string
    {
        return 'telegram-logger:suppressed:'.$config->chatKey();
    }
}
