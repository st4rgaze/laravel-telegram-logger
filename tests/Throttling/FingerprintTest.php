<?php

declare(strict_types=1);

use Monolog\Level;
use St4rgaze\TelegramLogger\Throttling\Fingerprint;

it('is stable for the same message and exception site', function (): void {
    $exception = new RuntimeException('x');

    expect(Fingerprint::for(logRecord(context: ['exception' => $exception]), 'telegram'))
        ->toBe(Fingerprint::for(logRecord(context: ['exception' => $exception, 'other' => 1]), 'telegram'))
        ->toMatch('/^[0-9a-f]{40}$/');
});

it('differs by channel, level, message and exception site', function (): void {
    $first = new RuntimeException('x');
    $second = new RuntimeException('x');

    $base = Fingerprint::for(logRecord(context: ['exception' => $first]), 'telegram');

    expect(Fingerprint::for(logRecord(context: ['exception' => $first]), 'ops'))->not->toBe($base)
        ->and(Fingerprint::for(logRecord(Level::Critical, context: ['exception' => $first]), 'telegram'))->not->toBe($base)
        ->and(Fingerprint::for(logRecord(message: 'other', context: ['exception' => $first]), 'telegram'))->not->toBe($base)
        ->and(Fingerprint::for(logRecord(context: ['exception' => $second]), 'telegram'))->not->toBe($base)
        ->and(Fingerprint::for(logRecord(context: ['exception' => new LogicException('x')]), 'telegram'))->not->toBe($base);
});
