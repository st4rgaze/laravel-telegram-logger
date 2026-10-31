<?php

declare(strict_types=1);

use Monolog\Level;
use St4rgaze\TelegramLogger\Exceptions\InvalidConfiguration;
use St4rgaze\TelegramLogger\Formatting\MessageComposer;
use St4rgaze\TelegramLogger\Formatting\MessageFormatter;
use St4rgaze\TelegramLogger\Formatting\TelegramText;
use St4rgaze\TelegramLogger\Logging\ExceptionSummary;
use St4rgaze\TelegramLogger\Logging\LogEntry;

final class ShoutingFormatter implements MessageFormatter
{
    public function format(LogEntry $entry): TelegramText
    {
        return TelegramText::make()->bold(mb_strtoupper($entry->message));
    }
}

function compose(array $entries, array $config = []): St4rgaze\TelegramLogger\Delivery\OutgoingMessage
{
    return app(MessageComposer::class)->compose($entries, channelConfig($config), str_repeat('f', 40));
}

it('renders one entry into html chunks', function (): void {
    $message = compose([logEntry()]);

    expect($message->chunks)->toHaveCount(1)
        ->and($message->chunks[0])->toStartWith('<b>🔴 ERROR</b> · Popout · production')
        ->and($message->trace)->toBeNull()
        ->and($message->silent)->toBeFalse();
});

it('sends levels below silent_below without a notification', function (): void {
    expect(compose([logEntry(['level' => Level::Warning])])->silent)->toBeTrue()
        ->and(compose([logEntry(['level' => Level::Warning])], ['silent_below' => 'warning'])->silent)->toBeFalse();
});

it('uses the configured formatter', function (): void {
    expect(compose([logEntry()], ['formatter' => ShoutingFormatter::class])->chunks)->toBe(['<b>PAYMENT FAILED</b>']);
});

it('rejects a formatter class that does not implement the contract', function (string $formatter): void {
    expect(fn (): St4rgaze\TelegramLogger\Logging\ChannelConfig => channelConfig(['formatter' => $formatter]))->toThrow(InvalidConfiguration::class);
})->with([stdClass::class, 'App\\Missing\\Formatter']);

it('joins a batch into one message', function (): void {
    $message = compose([logEntry(['message' => 'first']), logEntry(['message' => 'second', 'level' => Level::Critical])]);

    expect($message->chunks)->toHaveCount(1)
        ->and($message->chunks[0])->toContain('first')->toContain('──────────')->toContain('second');
});

it('attaches the trace file with the header as caption', function (): void {
    $message = compose([logEntry(['exceptions' => [new ExceptionSummary('RuntimeException', 'x', 'a.php', 1, '#0 {main}')]])]);

    expect($message->trace?->caption)->toBe('🔴 ERROR · Popout · production')
        ->and($message->trace?->filename)->toStartWith('trace-ffffffff-');
});

it('moves overflow into the trace file', function (): void {
    $message = compose([logEntry(['message' => str_repeat('word ', 4000)])], ['max_chunks' => 1]);

    expect($message->chunks)->toHaveCount(1)
        ->and($message->trace?->contents)->toContain('word');
});

it('masks the bot token anywhere in the outgoing message', function (): void {
    $message = compose([logEntry([
        'message' => 'Telegram call to bot'.TEST_TOKEN.' failed',
        'exceptions' => [new ExceptionSummary('RuntimeException', 'token '.TEST_TOKEN, 'a.php', 1, '#0 send('.TEST_TOKEN.')')],
    ])]);

    expect($message->chunks[0])->not->toContain(TEST_TOKEN)->toContain('bot***')
        ->and($message->trace?->contents)->not->toContain(TEST_TOKEN)
        ->and($message->trace?->caption)->not->toContain(TEST_TOKEN);
});

it('masks a token that a chunk boundary would split', function (): void {
    $message = compose([logEntry(['message' => str_repeat('x', 4036).TEST_TOKEN.str_repeat('y', 300)])], ['max_chunks' => 9]);

    $visible = implode('', array_map(fn (string $chunk): string => preg_replace('/^\(\d+\/\d+\) /', '', $chunk) ?? '', $message->chunks));

    expect($visible)->not->toContain('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdef1234')->toContain('***');
});

it('masks the secret part of the token on its own', function (): void {
    $message = compose([logEntry(['message' => 'echo ABCDEFGHIJKLMNOPQRSTUVWXYZabcdef1234 and 123456%3AABCDEFGHIJKLMNOPQRSTUVWXYZabcdef1234'])]);

    expect($message->chunks[0])->not->toContain('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdef1234');
});
