<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use St4rgaze\TelegramLogger\Delivery\Jobs\SendTelegramMessage;

beforeEach(function (): void {
    config([
        'app.name' => 'Popout',
        'telegram-logger.token' => TEST_TOKEN,
        'telegram-logger.chat_id' => TEST_CHAT_ID,
        'logging.channels.telegram' => ['driver' => 'telegram', 'delivery' => 'sync'],
    ]);
});

function healthyTelegram(array $overrides = []): Illuminate\Http\Client\Factory
{
    return fakeTelegram(array_merge([
        '*/getMe' => Http::response(['ok' => true, 'result' => ['username' => 'popout_alerts_bot']]),
        '*/getChat' => Http::response(['ok' => true, 'result' => ['title' => 'Popout Ops', 'type' => 'supergroup', 'is_forum' => true]]),
        '*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
    ], $overrides));
}

it('passes every check for a healthy setup', function (): void {
    $http = healthyTelegram();

    $this->artisan('telegram-logger:test')
        ->expectsOutputToContain('✓ Channel "telegram" uses the telegram driver')
        ->expectsOutputToContain('✓ Token format looks valid (123456:AB…34)')
        ->expectsOutputToContain('✓ Bot authenticated as @popout_alerts_bot')
        ->expectsOutputToContain('✓ Chat '.TEST_CHAT_ID.' found: "Popout Ops" (supergroup)')
        ->expectsOutputToContain('✓ Test message sent')
        ->doesntExpectOutputToContain(TEST_TOKEN)
        ->assertExitCode(0);

    $http->assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/sendMessage')
        && str_contains((string) $request['text'], 'Test message from Popout'));
});

it('diagnoses each failure', function (array $config, array $stubs, string $message): void {
    config($config);
    healthyTelegram($stubs);

    $this->artisan('telegram-logger:test')
        ->expectsOutputToContain('✗ '.$message)
        ->doesntExpectOutputToContain(TEST_TOKEN)
        ->assertExitCode(1);
})->with([
    'missing channel' => [['logging.channels.telegram' => null], [], 'Channel "telegram" is not defined in config/logging.php'],
    'wrong driver' => [['logging.channels.telegram' => ['driver' => 'single']], [], 'Channel "telegram" does not use the telegram driver'],
    'invalid config' => [['logging.channels.telegram' => ['driver' => 'telegram', 'delivery' => 'fast']], [], 'Telegram logger config [delivery]'],
    'no token' => [['telegram-logger.token' => null], [], 'No bot token: set TELEGRAM_LOGGER_BOT_TOKEN'],
    'bad token format' => [['telegram-logger.token' => 'not-a-token-at-all'], [], 'Token format looks invalid (not-a-tok…ll)'],
    'revoked token' => [[], ['*/getMe' => fn () => Http::response(['ok' => false, 'description' => 'Unauthorized'], 401)], 'Telegram rejected the token (revoked or mistyped)'],
    'telegram down' => [[], ['*/getMe' => fn () => Http::response('down', 502)], 'The Telegram API returned an invalid response with status 502.'],
    'no chat' => [['telegram-logger.chat_id' => null], [], 'No chat id: set TELEGRAM_LOGGER_CHAT_ID'],
    'unknown chat' => [[], ['*/getChat' => fn () => Http::response(['ok' => false, 'description' => 'Bad Request: chat not found'], 400)], 'Chat '.TEST_CHAT_ID.' not found: add the bot to the chat, or send /start to it'],
    'not a forum' => [['telegram-logger.message_thread_id' => 77], ['*/getChat' => fn () => Http::response(['ok' => true, 'result' => ['title' => 'Ops', 'type' => 'group']])], 'Thread 77: chat is not a forum'],
    'blocked' => [[], ['*/sendMessage' => fn () => Http::response(['ok' => false, 'description' => 'Forbidden: bot was blocked by the user'], 403)], 'Bot is blocked or lacks permission to post in this chat'],
    'send error' => [[], ['*/sendMessage' => fn () => Http::response(['ok' => false, 'description' => 'Bad Request: message thread not found'], 400)], 'Telegram API request failed with status 400: Bad Request: message thread not found'],
]);

it('accepts a thread on a forum chat', function (): void {
    config(['telegram-logger.message_thread_id' => 77]);
    healthyTelegram();

    $this->artisan('telegram-logger:test')
        ->expectsOutputToContain('✓ Thread 77 is set on a forum chat')
        ->assertExitCode(0);
});

it('names a private chat by its username', function (): void {
    healthyTelegram(['*/getChat' => Http::response(['ok' => true, 'result' => ['username' => 'indra', 'type' => 'private']])]);

    $this->artisan('telegram-logger:test')
        ->expectsOutputToContain('found: "indra" (private)')
        ->assertExitCode(0);
});

it('warns about the queue worker and can send through the queue', function (): void {
    config(['logging.channels.telegram' => ['driver' => 'telegram']]);
    healthyTelegram();
    Bus::fake();

    $this->artisan('telegram-logger:test', ['--queue' => true])
        ->expectsOutputToContain('✓ Test message queued')
        ->expectsOutputToContain('! Delivery is "queue": make sure a worker runs `queue:work`')
        ->assertExitCode(0);

    Bus::assertDispatched(SendTelegramMessage::class, fn (SendTelegramMessage $job): bool => $job->channel === 'telegram');
});

it('checks another channel', function (): void {
    config(['logging.channels.ops' => ['driver' => 'telegram', 'chat_id' => '99', 'delivery' => 'sync']]);
    healthyTelegram();

    $this->artisan('telegram-logger:test', ['channel' => 'ops'])
        ->expectsOutputToContain('✓ Chat 99 found')
        ->assertExitCode(0);
});
