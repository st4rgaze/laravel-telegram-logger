<?php

declare(strict_types=1);

use St4rgaze\TelegramLogger\Exceptions\InvalidConfiguration;
use St4rgaze\TelegramLogger\Redaction\Redactor;

function redactor(array $overrides = []): Redactor
{
    return Redactor::fromConfig(channelConfig($overrides));
}

it('masks a top level key', function (): void {
    expect(redactor()->redact(['password' => 'hunter2', 'order' => 42]))
        ->toBe(['password' => '[redacted]', 'order' => 42]);
});

it('masks keys at any depth', function (): void {
    expect(redactor()->redact(['user' => ['card' => ['cvv' => 123, 'last4' => '4242']]]))
        ->toBe(['user' => ['card' => ['cvv' => '[redacted]', 'last4' => '4242']]]);
});

it('matches keys case-insensitively', function (): void {
    expect(redactor()->redact(['Authorization' => 'Bearer x', 'PASSWORD' => 'y']))
        ->toBe(['Authorization' => '[redacted]', 'PASSWORD' => '[redacted]']);
});

it('masks the whole value when it is an array', function (): void {
    expect(redactor()->redact(['token' => ['a' => 1]]))->toBe(['token' => '[redacted]']);
});

it('leaves non-matching keys and list values untouched', function (): void {
    $data = ['items' => ['password', 'token'], 'count' => 2, 'ok' => true, 'none' => null];

    expect(redactor()->redact($data))->toBe($data);
});

it('uses the configured replacement', function (): void {
    expect(redactor(['redact' => ['replacement' => '***']])->redact(['secret' => 's']))->toBe(['secret' => '***']);
});

it('masks email addresses in text with the default pattern', function (): void {
    expect(redactor()->redactText('User budi.santoso+promo@example.co.id not found'))
        ->toBe('User [redacted] not found')
        ->and(redactor()->redactText('nothing to hide'))->toBe('nothing to hide');
});

it('applies custom patterns', function (): void {
    expect(redactor(['redact' => ['patterns' => ['/\b08\d{8,11}\b/']]])->redactText('call 081234567890 now'))
        ->toBe('call [redacted] now');
});

it('leaves text unchanged without patterns', function (): void {
    expect(redactor(['redact' => ['patterns' => []]])->redactText('budi@example.com'))->toBe('budi@example.com');
});

it('applies patterns to nested string leaves', function (): void {
    expect(redactor()->redact(['customer' => ['note' => 'mail budi@example.com']]))
        ->toBe(['customer' => ['note' => 'mail [redacted]']]);
});

it('rejects an invalid pattern', function (): void {
    expect(fn (): Redactor => redactor(['redact' => ['patterns' => ['/unclosed(/']]]))
        ->toThrow(InvalidConfiguration::class);
});

it('masks text with invalid utf-8 when the pattern uses the u modifier', function (): void {
    expect(redactor(['redact' => ['patterns' => ['/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/iu']]])->redactText("budi@example.com \xC3\x28"))
        ->toStartWith('[redacted]')->not->toContain('budi@example.com');
});

it('replaces the whole text when a pattern cannot be evaluated', function (): void {
    $previous = ini_set('pcre.backtrack_limit', '10');
    ini_set('pcre.jit', '0');

    try {
        $result = redactor(['redact' => ['patterns' => ['/(a+)+b/']]])->redactText(str_repeat('a', 40).'c budi');
    } finally {
        ini_set('pcre.backtrack_limit', (string) $previous);
        ini_set('pcre.jit', '1');
    }

    expect($result)->toBe('[redacted]');
});

it('masks the password fields of common auth starters by default', function (): void {
    expect(redactor()->redact(['current_password' => 'a', 'new_password' => 'b', '_token' => 'c', 'x-api-key' => 'd']))
        ->toBe(['current_password' => '[redacted]', 'new_password' => '[redacted]', '_token' => '[redacted]', 'x-api-key' => '[redacted]']);
});

it('masks verification hashes and signatures by default', function (): void {
    expect(redactor()->redact(['hash' => 'a', 'signature' => 'b']))->toBe(['hash' => '[redacted]', 'signature' => '[redacted]']);
});
