<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Logging;

use Illuminate\Support\Arr;
use Monolog\Level;
use St4rgaze\TelegramLogger\Exceptions\InvalidConfiguration;

/**
 * @internal
 */
final readonly class ConfigReader
{
    /**
     * Create a reader over already-merged config values.
     *
     * @param  array<array-key, mixed>  $values
     */
    public function __construct(private array $values) {}

    /**
     * Merge channel values over global values: associative arrays per key, lists replaced.
     *
     * @param  array<array-key, mixed>  $global
     * @param  array<array-key, mixed>  $channel
     * @return array<array-key, mixed>
     */
    public static function merge(array $global, array $channel): array
    {
        foreach ($channel as $key => $value) {
            $current = $global[$key] ?? null;

            $global[$key] = is_array($value) && is_array($current) && ! array_is_list($value) && ! array_is_list($current)
                ? self::merge($current, $value)
                : $value;
        }

        return $global;
    }

    /**
     * Read an optional string; blank values become null.
     */
    public function nullableString(string $key): ?string
    {
        $value = $this->get($key);

        if ($value === null) {
            return null;
        }

        if (! is_string($value) && ! is_int($value)) {
            throw InvalidConfiguration::invalidValue($key, 'a string');
        }

        $value = mb_trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Read a required, non-blank string.
     */
    public function string(string $key): string
    {
        return $this->nullableString($key) ?? throw InvalidConfiguration::invalidValue($key, 'a non-empty string');
    }

    /**
     * Read an optional positive integer; blank values become null.
     */
    public function nullablePositiveInt(string $key): ?int
    {
        $value = $this->get($key);

        if ($value === null || $value === '') {
            return null;
        }

        return $this->positiveInt($key);
    }

    /**
     * Read a positive integer, accepting numeric strings.
     */
    public function positiveInt(string $key): int
    {
        $value = $this->get($key);

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            $value = (int) $value;
        }

        if (! is_int($value) || $value < 1) {
            throw InvalidConfiguration::invalidValue($key, 'a positive integer');
        }

        return $value;
    }

    /**
     * Read a positive number, accepting numeric strings.
     */
    public function positiveFloat(string $key): float
    {
        $value = $this->get($key);

        if (! is_numeric($value)) {
            throw InvalidConfiguration::invalidValue($key, 'a positive number');
        }

        $value = (float) $value;

        if ($value <= 0) {
            throw InvalidConfiguration::invalidValue($key, 'a positive number');
        }

        return $value;
    }

    /**
     * Read a boolean, accepting the usual env spellings.
     */
    public function bool(string $key): bool
    {
        $value = $this->get($key);

        if (is_bool($value)) {
            return $value;
        }

        return match (is_string($value) || is_int($value) ? mb_strtolower((string) $value) : null) {
            '1', 'true', 'on', 'yes' => true,
            '0', 'false', 'off', 'no' => false,
            default => throw InvalidConfiguration::invalidValue($key, 'a boolean'),
        };
    }

    /**
     * Read a Monolog level name, falling back to the given default when missing.
     */
    public function level(string $key, Level $default): Level
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        if (! is_string($value)) {
            throw InvalidConfiguration::invalidValue($key, 'a log level name');
        }

        foreach (Level::cases() as $level) {
            if (strcasecmp($level->getName(), $value) === 0) {
                return $level;
            }
        }

        throw InvalidConfiguration::invalidValue($key, 'a log level name');
    }

    /**
     * Read a list of non-empty strings.
     *
     * @return list<string>
     */
    public function stringList(string $key): array
    {
        $value = $this->get($key);

        if (! is_array($value)) {
            throw InvalidConfiguration::invalidValue($key, 'a list of strings');
        }

        $strings = [];

        foreach ($value as $item) {
            if (! is_string($item) || $item === '') {
                throw InvalidConfiguration::invalidValue($key, 'a list of strings');
            }

            $strings[] = $item;
        }

        return $strings;
    }

    /**
     * Read a list of valid regular expressions.
     *
     * @return list<string>
     */
    public function patternList(string $key): array
    {
        $patterns = $this->stringList($key);

        foreach ($patterns as $pattern) {
            if (@preg_match($pattern, '') === false) {
                throw InvalidConfiguration::invalidValue($key, 'a list of valid regular expressions');
            }
        }

        return $patterns;
    }

    /**
     * Get a raw value by dot-notation key.
     */
    private function get(string $key): mixed
    {
        return Arr::get($this->values, $key);
    }
}
