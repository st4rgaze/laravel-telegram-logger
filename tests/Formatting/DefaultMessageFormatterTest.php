<?php

declare(strict_types=1);

use Monolog\Level;
use St4rgaze\TelegramLogger\Formatting\DefaultMessageFormatter;
use St4rgaze\TelegramLogger\Logging\ExceptionSummary;

function formatted(array $overrides = []): string
{
    return (new DefaultMessageFormatter)->format(logEntry($overrides))->plainText();
}

it('renders the minimal layout', function (): void {
    expect(formatted())->toBe("🔴 ERROR · Popout · production\nPayment failed\n\n🕒 2026-11-01 10:22:03 WIB");
});

it('shows the icon and name of every level', function (Level $level, string $header): void {
    expect(formatted(['level' => $level]))->toStartWith($header.' · Popout');
})->with([
    [Level::Debug, '🔍 DEBUG'],
    [Level::Info, 'ℹ️ INFO'],
    [Level::Notice, '📝 NOTICE'],
    [Level::Warning, '⚠️ WARNING'],
    [Level::Error, '🔴 ERROR'],
    [Level::Critical, '🚨 CRITICAL'],
    [Level::Alert, '🆘 ALERT'],
    [Level::Emergency, '💥 EMERGENCY'],
]);

it('styles the header bold and the exception as code', function (): void {
    $text = (new DefaultMessageFormatter)->format(logEntry([
        'exceptions' => [new ExceptionSummary('App\\Exceptions\\PaymentException', 'Card declined', 'app/Services/PaymentService.php', 42, '#0 {main}')],
    ]));

    $html = implode('', array_map(fn (St4rgaze\TelegramLogger\Formatting\Segment $segment): string => $segment->style->open().$segment->text.$segment->style->close(), $text->segments()));

    expect($html)->toContain('<b>🔴 ERROR</b>')
        ->toContain('<code>PaymentException: Card declined</code>')
        ->toContain('<code>app/Services/PaymentService.php:42</code>');
});

it('shows the exception without repeating an identical message', function (): void {
    expect(formatted([
        'message' => 'Card declined',
        'exceptions' => [new ExceptionSummary('App\\Exceptions\\PaymentException', 'Card declined', 'app/Pay.php', 42, '')],
    ]))->toContain("Card declined\n\nPaymentException\napp/Pay.php:42");
});

it('shows the request line', function (): void {
    expect(formatted(['request' => ['url' => 'https://popout.id/checkout', 'method' => 'POST', 'route' => 'checkout.store', 'user_id' => '17', 'ip' => '10.0.0.1']]))
        ->toContain('🌐 POST https://popout.id/checkout · checkout.store · user 17 · ip 10.0.0.1');
});

it('shows context and extra as pretty json', function (): void {
    expect(formatted(['context' => ['order' => 42, 'note' => 'é/x'], 'extra' => ['tag' => 'a']]))
        ->toContain("{\n    \"order\": 42,\n    \"note\": \"é/x\"\n}")
        ->toContain("{\n    \"tag\": \"a\"\n}");
});

it('omits empty sections', function (): void {
    expect(formatted())->not->toContain('🌐')->not->toContain('{')->not->toContain('➕');
});

it('reports suppressed duplicates in the footer', function (): void {
    expect(formatted(['suppressedCount' => 14]))->toEndWith('🕒 2026-11-01 10:22:03 WIB · ➕ 14 similar suppressed');
});
