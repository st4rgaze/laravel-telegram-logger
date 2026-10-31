<?php

declare(strict_types=1);

use St4rgaze\TelegramLogger\Logging\ReentrancyGuard;

it('is active only while running the callback', function (): void {
    $inside = null;

    ReentrancyGuard::run(function () use (&$inside): void {
        $inside = ReentrancyGuard::active();
    });

    expect($inside)->toBeTrue()->and(ReentrancyGuard::active())->toBeFalse();
});

it('resets after the callback throws', function (): void {
    try {
        ReentrancyGuard::run(fn () => throw new RuntimeException('boom'));
    } catch (RuntimeException) {
    }

    expect(ReentrancyGuard::active())->toBeFalse();
});

it('stays active after a nested run returns', function (): void {
    $afterNested = null;

    ReentrancyGuard::run(function () use (&$afterNested): void {
        ReentrancyGuard::run(fn (): null => null);
        $afterNested = ReentrancyGuard::active();
    });

    expect($afterNested)->toBeTrue();
});
