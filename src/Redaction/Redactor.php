<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Redaction;

use St4rgaze\TelegramLogger\Logging\ChannelConfig;

/**
 * @internal
 */
final readonly class Redactor
{
    /**
     * The lowercased keys to mask, as a lookup set.
     *
     * @var array<string, true>
     */
    private array $keys;

    /**
     * Create a redactor for the given keys and patterns.
     *
     * @param  list<string>  $keys
     * @param  list<string>  $patterns
     */
    public function __construct(array $keys, private array $patterns, private string $replacement)
    {
        $this->keys = array_fill_keys(array_map(mb_strtolower(...), $keys), true);
    }

    /**
     * Create a redactor from a channel's configuration.
     */
    public static function fromConfig(ChannelConfig $config): self
    {
        return new self($config->redactKeys, $config->redactPatterns, $config->redactReplacement);
    }

    /**
     * Mask matching keys at any depth and apply the patterns to every string value.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function redact(array $data): array
    {
        $redacted = [];

        foreach ($data as $key => $value) {
            $redacted[$key] = match (true) {
                is_string($key) && isset($this->keys[mb_strtolower($key)]) => $this->replacement,
                is_array($value) => $this->redact($value),
                is_string($value) => $this->redactText($value),
                default => $value,
            };
        }

        return $redacted;
    }

    /**
     * Mask every pattern match in the text.
     */
    public function redactText(string $text): string
    {
        $text = mb_scrub($text, 'UTF-8');

        foreach ($this->patterns as $pattern) {
            $replaced = preg_replace($pattern, $this->replacement, $text);

            if ($replaced === null) {
                return $this->replacement;
            }

            $text = $replaced;
        }

        return $text;
    }
}
