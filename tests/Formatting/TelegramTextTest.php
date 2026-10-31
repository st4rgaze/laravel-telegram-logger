<?php

declare(strict_types=1);

use St4rgaze\TelegramLogger\Enums\SegmentStyle;
use St4rgaze\TelegramLogger\Formatting\Segment;
use St4rgaze\TelegramLogger\Formatting\TelegramText;

it('collects styled segments in order', function (): void {
    $text = TelegramText::make()->bold('ERROR')->text(' · App')->line()->italic('i')->code('c')->pre('p')->quote('q');

    expect(array_map(fn (Segment $segment): array => [$segment->text, $segment->style], $text->segments()))->toBe([
        ['ERROR', SegmentStyle::Bold],
        [' · App', SegmentStyle::Plain],
        ["\n", SegmentStyle::Plain],
        ['i', SegmentStyle::Italic],
        ['c', SegmentStyle::Code],
        ['p', SegmentStyle::Pre],
        ['q', SegmentStyle::Quote],
    ]);
});

it('returns the plain text', function (): void {
    expect(TelegramText::make()->bold('a')->line(2)->code('<b>')->plainText())->toBe("a\n\n<b>");
});

it('ignores empty strings', function (): void {
    expect(TelegramText::make()->text('')->bold('')->line(0)->isEmpty())->toBeTrue()
        ->and(TelegramText::make()->text('x')->isEmpty())->toBeFalse();
});

it('appends another text', function (): void {
    expect(TelegramText::make()->text('a')->append(TelegramText::make()->bold('b'))->plainText())->toBe('ab');
});

it('renders html tags per style', function (SegmentStyle $style, string $open, string $close): void {
    expect($style->open())->toBe($open)->and($style->close())->toBe($close);
})->with([
    [SegmentStyle::Plain, '', ''],
    [SegmentStyle::Bold, '<b>', '</b>'],
    [SegmentStyle::Italic, '<i>', '</i>'],
    [SegmentStyle::Code, '<code>', '</code>'],
    [SegmentStyle::Pre, '<pre>', '</pre>'],
    [SegmentStyle::Quote, '<blockquote>', '</blockquote>'],
]);
