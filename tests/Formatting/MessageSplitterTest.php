<?php

declare(strict_types=1);

use St4rgaze\TelegramLogger\Formatting\MessageSplitter;
use St4rgaze\TelegramLogger\Formatting\TelegramText;

function visibleLength(string $chunk): int
{
    return mb_strlen(html_entity_decode(strip_tags($chunk), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

it('returns a single chunk without prefix when it fits exactly', function (): void {
    $result = (new MessageSplitter)->split(TelegramText::make()->text(str_repeat('a', 4096)), 3);

    expect($result->chunks)->toHaveCount(1)
        ->and($result->chunks[0])->toBe(str_repeat('a', 4096))
        ->and($result->overflow)->toBe('');
});

it('escapes html and wraps styles', function (): void {
    $result = (new MessageSplitter)->split(TelegramText::make()->bold('A & B')->text(' <x>')->pre('1 < 2'), 3);

    expect($result->chunks)->toBe(['<b>A &amp; B</b> &lt;x&gt;<pre>1 &lt; 2</pre>']);
});

it('splits above the limit and prefixes each chunk', function (): void {
    $result = (new MessageSplitter)->split(TelegramText::make()->text(str_repeat('a', 4097)), 3);

    expect($result->chunks)->toHaveCount(2)
        ->and($result->chunks[0])->toStartWith('(1/2) ')
        ->and($result->chunks[1])->toStartWith('(2/2) ')
        ->and(implode('', array_map(fn (string $chunk): string => mb_substr($chunk, 6), $result->chunks)))->toBe(str_repeat('a', 4097));
});

it('counts characters not bytes', function (string $character): void {
    expect((new MessageSplitter)->split(TelegramText::make()->text(str_repeat($character, 4096)), 3)->chunks)->toHaveCount(1);
})->with(['é', '😀', '漢']);

it('never exceeds the limit in visible characters', function (): void {
    $text = TelegramText::make()->bold('🔴 ERROR')->line()->pre(str_repeat("Kesalahan pembayaran & <timeout> 😀\n", 600));

    $chunks = (new MessageSplitter)->split($text, 99)->chunks;

    expect(count($chunks))->toBeGreaterThan(2);

    foreach ($chunks as $chunk) {
        expect(visibleLength($chunk))->toBeLessThanOrEqual(4096);
    }
});

it('keeps tags balanced when a pre segment spans chunks', function (): void {
    $chunks = (new MessageSplitter)->split(TelegramText::make()->pre(str_repeat("line\n", 2000)), 9)->chunks;

    expect($chunks)->toHaveCount(3);

    foreach ($chunks as $chunk) {
        expect(mb_substr_count($chunk, '<pre>'))->toBe(1)
            ->and(mb_substr_count($chunk, '</pre>'))->toBe(1);
    }
});

it('never splits an html entity', function (): void {
    $chunks = (new MessageSplitter)->split(TelegramText::make()->text(str_repeat('&', 9000)), 9)->chunks;

    foreach ($chunks as $chunk) {
        expect(preg_match('/&(?!amp;|lt;|gt;)/', $chunk))->toBe(0);
    }
});

it('prefers breaking at a newline, then a space', function (): void {
    $newline = (new MessageSplitter)->split(TelegramText::make()->text(str_repeat('a', 4000)."\n".str_repeat('b', 200)), 3)->chunks;
    $space = (new MessageSplitter)->split(TelegramText::make()->text(str_repeat('a', 4000).' '.str_repeat('b', 200)), 3)->chunks;

    expect($newline[0])->toBe('(1/2) '.str_repeat('a', 4000)."\n")
        ->and($newline[1])->toBe('(2/2) '.str_repeat('b', 200))
        ->and($space[0])->toBe('(1/2) '.str_repeat('a', 4000).' ')
        ->and($space[1])->toBe('(2/2) '.str_repeat('b', 200));
});

it('hard-cuts a single long word', function (): void {
    $chunks = (new MessageSplitter)->split(TelegramText::make()->code(str_repeat('x', 10_000)), 9)->chunks;

    expect($chunks)->toHaveCount(3)
        ->and(array_sum(array_map(fn (string $chunk): int => mb_substr_count($chunk, 'x'), $chunks)))->toBe(10_000);
});

it('moves text beyond the chunk limit into the overflow with a note', function (): void {
    $result = (new MessageSplitter)->split(TelegramText::make()->text(str_repeat('a', 9000).'TAIL'), 2);

    expect($result->chunks)->toHaveCount(2)
        ->and($result->chunks[1])->toEndWith(htmlspecialchars(MessageSplitter::OVERFLOW_NOTE, ENT_NOQUOTES))
        ->and($result->chunks[0])->toStartWith('(1/2) ')
        ->and($result->overflow)->toEndWith('TAIL')
        ->and(mb_strlen($result->overflow) + 2 * (4096 - 6 - mb_strlen(MessageSplitter::OVERFLOW_NOTE)))->toBe(9004);
});

it('scrubs invalid utf-8', function (): void {
    $chunk = (new MessageSplitter)->split(TelegramText::make()->text("bad \xC3\x28 byte"), 3)->chunks[0];

    expect(mb_check_encoding($chunk, 'UTF-8'))->toBeTrue();
});
