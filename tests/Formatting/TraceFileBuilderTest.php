<?php

declare(strict_types=1);

use St4rgaze\TelegramLogger\Formatting\TraceFileBuilder;
use St4rgaze\TelegramLogger\Logging\ExceptionSummary;

function withException(): array
{
    return ['exceptions' => [
        new ExceptionSummary('RuntimeException', 'Card declined', 'app/Pay.php', 42, "#0 app/Http/Pay.php(10): charge()\n#1 {main}"),
        new ExceptionSummary('LogicException', 'Gateway timeout', 'app/Gateway.php', 7, '#0 {main}'),
    ], 'request' => ['url' => 'https://popout.id/checkout'], 'context' => ['order' => 42]];
}

it('builds nothing without an exception or overflow', function (): void {
    expect((new TraceFileBuilder)->build([logEntry()], '', channelConfig(), str_repeat('a', 40), 'caption'))->toBeNull();
});

it('builds nothing for an exception when trace attachment is disabled', function (): void {
    expect((new TraceFileBuilder)->build([logEntry(withException())], '', channelConfig(['trace' => ['attach' => false]]), str_repeat('a', 40), 'c'))->toBeNull();
});

it('describes the whole exception chain with request and context', function (): void {
    $file = (new TraceFileBuilder)->build([logEntry(withException())], '', channelConfig(), 'abcdef0123456789', '🔴 ERROR · Popout');

    expect($file->filename)->toMatch('/^trace-abcdef01-20261101102203\.txt$/')
        ->and($file->caption)->toBe('🔴 ERROR · Popout')
        ->and($file->contents)
        ->toContain('RuntimeException: Card declined')
        ->toContain('at app/Pay.php:42')
        ->toContain('#0 app/Http/Pay.php(10): charge()')
        ->toContain('Caused by LogicException: Gateway timeout')
        ->toContain('url: https://popout.id/checkout')
        ->toContain('"order": 42');
});

it('includes overflow text even when traces are disabled', function (): void {
    $file = (new TraceFileBuilder)->build([logEntry()], 'THE REST', channelConfig(['trace' => ['attach' => false]]), str_repeat('b', 40), 'c');

    expect($file->contents)->toContain('THE REST')->not->toContain('Exception');
});

it('truncates at the configured size without breaking utf-8', function (): void {
    $file = (new TraceFileBuilder)->build([logEntry()], str_repeat('é', 5000), channelConfig(['trace' => ['max_bytes' => 1001]]), str_repeat('c', 40), 'c');

    expect($file->contents)->toEndWith("\n[truncated at 1001 bytes]")
        ->and(mb_strlen($file->contents))->toBeLessThanOrEqual(1001 + mb_strlen("\n[truncated at 1001 bytes]"))
        ->and(mb_check_encoding($file->contents, 'UTF-8'))->toBeTrue();
});

it('limits the caption to 1024 characters', function (): void {
    expect(mb_strlen((new TraceFileBuilder)->build([logEntry()], 'x', channelConfig(), str_repeat('d', 40), str_repeat('é', 2000))->caption))->toBe(1024);
});

it('truncates by bytes, not characters', function (): void {
    $file = (new TraceFileBuilder)->build([logEntry()], str_repeat('Ж', 3000), channelConfig(['trace' => ['max_bytes' => 4000]]), str_repeat('e', 40), 'c');

    expect(mb_strlen($file->contents))->toBeLessThanOrEqual(4000 + mb_strlen("\n[truncated at 4000 bytes]"));
});
