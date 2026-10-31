<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Diagnostics;

use Illuminate\Support\Facades\Config;
use St4rgaze\TelegramLogger\Delivery\Dispatcher;
use St4rgaze\TelegramLogger\Delivery\OutgoingMessage;
use St4rgaze\TelegramLogger\Enums\CheckStatus;
use St4rgaze\TelegramLogger\Enums\Delivery;
use St4rgaze\TelegramLogger\Exceptions\InvalidConfiguration;
use St4rgaze\TelegramLogger\Logging\ChannelConfig;
use St4rgaze\TelegramLogger\Telegram\SecretMasker;
use St4rgaze\TelegramLogger\Telegram\TelegramApiException;
use St4rgaze\TelegramLogger\Telegram\TelegramClient;

/**
 * @internal
 */
final readonly class Diagnoser
{
    /**
     * Create a diagnoser.
     */
    public function __construct(
        private TelegramClient $client,
        private Dispatcher $dispatcher,
    ) {}

    /**
     * Run the setup checks in order, stopping after the first failure.
     *
     * @return list<Check>
     */
    public function run(string $channel, bool $viaQueue): array
    {
        $checks = [];

        foreach ($this->checks($channel, $viaQueue) as $check) {
            $checks[] = $check;

            if ($check->status === CheckStatus::Fail) {
                break;
            }
        }

        return $checks;
    }

    /**
     * Yield each check as it runs.
     *
     * @return iterable<Check>
     */
    private function checks(string $channel, bool $viaQueue): iterable
    {
        $channelConfig = Config::get("logging.channels.{$channel}");

        if (! is_array($channelConfig)) {
            yield Check::fail(sprintf('Channel "%s" is not defined in config/logging.php', $channel));

            return;
        }

        if (($channelConfig['driver'] ?? null) !== 'telegram') {
            yield Check::fail(sprintf('Channel "%s" does not use the telegram driver', $channel));

            return;
        }

        yield Check::pass(sprintf('Channel "%s" uses the telegram driver', $channel));

        try {
            $config = ChannelConfig::forChannel($channel);
        } catch (InvalidConfiguration $invalidConfiguration) {
            yield Check::fail($invalidConfiguration->getMessage());

            return;
        }

        yield $this->tokenFormat($config);
        yield $this->bot($config);

        if ($config->chatId === null) {
            yield Check::fail('No chat id: set TELEGRAM_LOGGER_CHAT_ID');

            return;
        }

        yield from $this->chat($config);
        yield $this->testMessage($config, $viaQueue);

        if ($config->delivery === Delivery::Queue) {
            yield Check::warn('Delivery is "queue": make sure a worker runs `queue:work`');
        }
    }

    /**
     * Check that a token is present and shaped like a bot token.
     */
    private function tokenFormat(ChannelConfig $config): Check
    {
        if ($config->token === null) {
            return Check::fail('No bot token: set TELEGRAM_LOGGER_BOT_TOKEN');
        }

        $display = SecretMasker::display($config->token);

        return preg_match('/^\d+:[A-Za-z0-9_-]{30,}$/', $config->token) === 1
            ? Check::pass(sprintf('Token format looks valid (%s)', $display))
            : Check::fail(sprintf('Token format looks invalid (%s)', $display));
    }

    /**
     * Check that Telegram accepts the token.
     */
    private function bot(ChannelConfig $config): Check
    {
        try {
            $username = $this->client->getMe($config)->string('username');
        } catch (TelegramApiException $telegramApiException) {
            return Check::fail($telegramApiException->status === 401
                ? 'Telegram rejected the token (revoked or mistyped)'
                : $telegramApiException->getMessage());
        }

        return Check::pass(sprintf('Bot authenticated as @%s', $username ?? 'unknown'));
    }

    /**
     * Check that the bot can see the chat and, when set, that the thread belongs to a forum.
     *
     * @return iterable<Check>
     */
    private function chat(ChannelConfig $config): iterable
    {
        try {
            $chat = $this->client->getChat($config);
        } catch (TelegramApiException $telegramApiException) {
            yield Check::fail($telegramApiException->status === 400
                ? sprintf('Chat %s not found: add the bot to the chat, or send /start to it', $config->chatId)
                : $telegramApiException->getMessage());

            return;
        }

        $title = $chat->string('title') ?? $chat->string('username') ?? $chat->string('first_name') ?? '?';

        yield Check::pass(sprintf('Chat %s found: "%s" (%s)', $config->chatId, $title, $chat->string('type') ?? '?'));

        if ($config->messageThreadId !== null) {
            yield $chat->bool('is_forum')
                ? Check::pass(sprintf('Thread %d is set on a forum chat', $config->messageThreadId))
                : Check::fail(sprintf('Thread %d: chat is not a forum', $config->messageThreadId));
        }
    }

    /**
     * Send a test message directly, or through the queue.
     */
    private function testMessage(ChannelConfig $config, bool $viaQueue): Check
    {
        $html = htmlspecialchars(
            sprintf('✅ Test message from %s (%s) via telegram-logger', Config::string('app.name', 'Laravel'), app()->environment()),
            ENT_NOQUOTES,
            'UTF-8',
        );

        if ($viaQueue) {
            $this->dispatcher->dispatch(new OutgoingMessage([$html], null, false), $this->queued($config));

            return Check::pass('Test message queued');
        }

        try {
            $this->client->sendMessage($config, $html, false);
        } catch (TelegramApiException $telegramApiException) {
            return Check::fail($telegramApiException->status === 403
                ? 'Bot is blocked or lacks permission to post in this chat'
                : $telegramApiException->getMessage());
        }

        return Check::pass('Test message sent');
    }

    /**
     * Get the channel's configuration with queue delivery.
     */
    private function queued(ChannelConfig $config): ChannelConfig
    {
        return ChannelConfig::from(
            Config::array('telegram-logger', []),
            array_merge(Config::array("logging.channels.{$config->name}", []), ['delivery' => Delivery::Queue->value]),
            $config->name,
        );
    }
}
