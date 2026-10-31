<?php

declare(strict_types=1);

use St4rgaze\TelegramLogger\Telegram\SecretMasker;

it('masks every occurrence of the secret', function (): void {
    expect(SecretMasker::mask('a '.TEST_TOKEN.' b '.TEST_TOKEN, TEST_TOKEN))->toBe('a *** b ***');
});

it('leaves text untouched without a secret', function (?string $secret): void {
    expect(SecretMasker::mask('plain text', $secret))->toBe('plain text');
})->with([null, '']);

it('displays a token with only its edges visible', function (): void {
    expect(SecretMasker::display(TEST_TOKEN))->toBe('123456:AB…34')
        ->and(SecretMasker::display(null))->toBe('(none)')
        ->and(SecretMasker::display('short'))->toBe('***');
});

it('masks the secret part after the bot id', function (): void {
    expect(SecretMasker::mask('url 123456%3AABCDEFGHIJKLMNOPQRSTUVWXYZabcdef1234', TEST_TOKEN))->toBe('url 123456%3A***');
});
