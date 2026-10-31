<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Telegram;

use SensitiveParameter;

/**
 * @internal
 */
final class SecretMasker
{
    /**
     * Replace every occurrence of the secret in the text with "***".
     */
    public static function mask(string $text, #[SensitiveParameter] ?string $secret): string
    {
        if ($secret === null || $secret === '') {
            return $text;
        }

        $text = str_replace($secret, '***', $text);
        $colon = mb_strpos($secret, ':');
        $secretPart = $colon === false ? '' : mb_substr($secret, $colon + 1);

        return mb_strlen($secretPart) >= 16 ? str_replace($secretPart, '***', $text) : $text;
    }

    /**
     * Get a display form of a token that only shows its edges.
     */
    public static function display(#[SensitiveParameter] ?string $token): string
    {
        if ($token === null || $token === '') {
            return '(none)';
        }

        if (mb_strlen($token) < 16) {
            return '***';
        }

        return mb_substr($token, 0, 9).'…'.mb_substr($token, -2);
    }
}
