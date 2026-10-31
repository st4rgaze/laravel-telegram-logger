# Laravel Telegram Logger

[![Tests](https://github.com/st4rgaze/laravel-telegram-logger/actions/workflows/tests.yml/badge.svg)](https://github.com/st4rgaze/laravel-telegram-logger/actions/workflows/tests.yml)
[![Formats](https://github.com/st4rgaze/laravel-telegram-logger/actions/workflows/formats.yml/badge.svg)](https://github.com/st4rgaze/laravel-telegram-logger/actions/workflows/formats.yml)
[![Packagist](https://img.shields.io/packagist/v/st4rgaze/laravel-telegram-logger)](https://packagist.org/packages/st4rgaze/laravel-telegram-logger)

A Telegram log channel for Laravel that is safe to leave on in production:

- **Never blocks your requests.** Messages are sent from a queued job by default, with retries that respect Telegram's rate limits.
- **Never leaks your bot token.** Not into your logs, exception messages, queue payloads, or HTTP monitoring such as Telescope, Pulse and Nightwatch.
- **Never floods your chat.** Identical errors are sent once per minute and each chat gets at most 20 Telegram messages a minute (a stack trace file counts as one); the next message tells you how many records were held back.
- **Redacts before sending.** Passwords, tokens and cookies in log context are masked, email addresses are masked in every text, stack traces never include call arguments, and database errors show their SQL with `?` placeholders.
- **Always valid.** Long messages are split on line breaks into valid HTML chunks; full stack traces arrive as an attached text file.
- **Tells you what is wrong.** `php artisan telegram-logger:test` checks the token, the chat and the topic and sends a test message.

Requires PHP 8.3+ and Laravel 12 or 13.

## Installation

```bash
composer require st4rgaze/laravel-telegram-logger
```

1. Create a bot with [@BotFather](https://t.me/BotFather) (`/newbot`) and copy its token.
2. Add the bot to your group (or send it `/start` for a private chat) and get the chat id, for example from `https://api.telegram.org/bot<token>/getUpdates` after posting a message in the chat.
3. Add both to `.env`:

```dotenv
TELEGRAM_LOGGER_BOT_TOKEN=123456789:AA...
TELEGRAM_LOGGER_CHAT_ID=-1001234567890
```

4. Add a channel to `config/logging.php`:

```php
'channels' => [
    // ...

    'telegram' => [
        'driver' => 'telegram',
        'level' => 'error',
    ],
],
```

Add it to your stack (`'channels' => ['single', 'telegram']`) or set `LOG_STACK=single,telegram`.

5. Check the setup:

```bash
php artisan telegram-logger:test
```

```
✓ Channel "telegram" uses the telegram driver
✓ Token format looks valid (123456789…xy)
✓ Bot authenticated as @your_alerts_bot
✓ Chat -1001234567890 found: "Ops" (supergroup)
✓ Test message sent
! Delivery is "queue": make sure a worker runs `queue:work`
```

## Delivery

By default (`TELEGRAM_LOGGER_DELIVERY=queue`) each message is sent by a queued job chain, so a slow or unreachable Telegram never slows your application down. Run a worker for the queue you configure:

```dotenv
TELEGRAM_LOGGER_QUEUE_CONNECTION=redis   # default: your app's default connection
TELEGRAM_LOGGER_QUEUE=alerts             # default: the connection's default queue
```

Each job makes up to three attempts: it waits `retry_after` when Telegram rate-limits (capped at 300 seconds), and 10 then 30 seconds after a server or network error. A message that still cannot be delivered is reported in one line to `fallback_channel` (or PHP's `error_log()`), never to Telegram again, so a Telegram outage cannot cause a logging loop.

Applications without a queue worker can send immediately:

```dotenv
TELEGRAM_LOGGER_DELIVERY=sync
```

Sync delivery waits for Telegram (2 second connect, `TELEGRAM_LOGGER_TIMEOUT` total, default 5) during the request that logged.

## Production notes

- **Use a cache and queue that do not live in your main database.** With the database queue, a job pushed inside a transaction that rolls back disappears with it, and during a database outage (when you need alerts most) neither the throttle nor the queue works. Redis or SQS for the queue and Redis or Memcached for `throttle.cache_store` avoid both. The jobs are always dispatched immediately, even when the connection uses `after_commit`.
- **Broker size limits.** A queued message carries its stack trace file in the job payload. The default `trace.max_bytes` (200 KB) fits SQS (256 KB); lower it for Beanstalkd (64 KB by default).
- **Workers and Octane.** See [one message per request](#one-message-per-request) before buffering in long-running processes.

## Configuration

Publishing the config file is optional:

```bash
php artisan vendor:publish --tag=telegram-logger-config
```

| Key | Env | Default |
|---|---|---|
| `token` | `TELEGRAM_LOGGER_BOT_TOKEN` | — |
| `chat_id` | `TELEGRAM_LOGGER_CHAT_ID` | — |
| `message_thread_id` | `TELEGRAM_LOGGER_MESSAGE_THREAD_ID` | — (forum topic id) |
| `api_host` | `TELEGRAM_LOGGER_API_HOST` | `https://api.telegram.org` |
| `proxy` | `TELEGRAM_LOGGER_PROXY` | — |
| `timeout` | `TELEGRAM_LOGGER_TIMEOUT` | `5` |
| `delivery` | `TELEGRAM_LOGGER_DELIVERY` | `queue` |
| `queue.connection` / `queue.name` | `TELEGRAM_LOGGER_QUEUE_CONNECTION` / `TELEGRAM_LOGGER_QUEUE` | app default |
| `throttle.enabled` | | `true` |
| `throttle.dedupe_seconds` | | `60` |
| `throttle.max_per_minute` | | `20` |
| `throttle.cache_store` | | app default |
| `redact.keys` | | `password`, `current_password`, `new_password`, `_token`, `token`, `secret`, `authorization`, `cookie`, `api_key`, `x-api-key`, `hash`, `signature`, … |
| `redact.patterns` | | email addresses |
| `redact.replacement` | | `[redacted]` |
| `request_context.{url,method,route,user_id}` | | `true` |
| `request_context.ip` | | `false` |
| `trace.attach` / `trace.max_bytes` | | `true` / `200000` |
| `max_chunks` | | `3` |
| `silent_below` | | `error` (lower levels arrive without a notification sound) |
| `formatter` | | `DefaultMessageFormatter::class` |
| `fallback_channel` | | `null` (PHP `error_log()`) |

Invalid values (an unknown `delivery`, a non-numeric `timeout`, a broken regex, a non-https `api_host`…) are reported when the channel is created: Laravel falls back to its emergency logger and logs the reason. A missing token or chat id is not an error; the channel simply does nothing until both are set.

### Several chats

Every key can be overridden per channel. Nested arrays are merged key by key; lists such as `redact.keys` are replaced.

```php
'telegram' => [
    'driver' => 'telegram',
    'level' => 'error',
],

'telegram-ops' => [
    'driver' => 'telegram',
    'level' => 'warning',
    'chat_id' => env('TELEGRAM_OPS_CHAT_ID'),
    'message_thread_id' => env('TELEGRAM_OPS_TOPIC_ID'),
    'throttle' => ['dedupe_seconds' => 300],
],
```

Channels built on demand with `Log::build()` must use sync delivery, because a queued job can only find the configuration of channels defined in `config/logging.php`:

```php
Log::build(['driver' => 'telegram', 'delivery' => 'sync', 'chat_id' => $chatId])->info('Deployed');
```

### One message per request

Wrap the handler in Monolog's `BufferHandler` with a `tap` class to send everything a request logged as a single message:

```php
namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Handler\BufferHandler;
use Monolog\Handler\HandlerInterface;

final class BufferTelegram
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        $monolog->setHandlers(array_map(
            fn (HandlerInterface $handler): BufferHandler => new BufferHandler($handler),
            $monolog->getHandlers(),
        ));
    }
}
```

```php
'telegram' => [
    'driver' => 'telegram',
    'level' => 'error',
    'tap' => [App\Logging\BufferTelegram::class],
],
```

Under PHP-FPM the buffer is flushed at the end of the request and the records are joined into one message. Queue workers and Octane are long-running processes that only flush on shutdown, so give the buffer a limit there (`new BufferHandler($handler, bufferLimit: 50, flushOnOverflow: true)`) or apply the tap only to HTTP channels.

## Redaction

Everything is redacted before it is formatted, queued or attached:

- **Keys** listed in `redact.keys` are masked at any depth in context and extra, matched case-insensitively.
- **Patterns** in `redact.patterns` are masked in every text: the message, context values, exception messages and stack traces. Email addresses are masked by default. Add your own:

```php
'redact' => [
    'patterns' => [
        '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', // email (default)
        '/\b(\+62|0)8\d{8,11}\b/',                  // Indonesian mobile numbers
        '/\b\d{16}\b/',                              // 16-digit ids such as NIK
    ],
],
```

Phone and id patterns are not enabled by default because they also match order numbers and other ids you probably want to see.

- **Stack traces** list file, line and function for each frame, never the call arguments.
- **Database errors** (`Illuminate\Database\QueryException`) are shown with the SQL and `?` placeholders, not the bound values. The database driver's own message can still quote a value (MySQL "Duplicate entry '…'", PostgreSQL "Key (…)=(…)"); add a pattern if that matters to you.
- **Request context** shows the real path (`/orders/123`), but route parameters whose names contain a redacted key stay placeholders (`/reset-password/{token}`, `/invites/{invite_token}`, `/email/verify/7/{hash}`), and the patterns apply too. The query string is never sent and the IP address is off by default.

Messages stay in the chat history and every member can read them. Keep the alert chat small and private.

## Custom formatter

Implement `MessageFormatter` and set its class in `formatter`. Formatters build a `TelegramText` from typed segments; the package escapes, splits and wraps them, so a formatter can never send broken HTML or an oversized message. The entry you receive is already redacted.

```php
use St4rgaze\TelegramLogger\Formatting\MessageFormatter;
use St4rgaze\TelegramLogger\Formatting\TelegramText;
use St4rgaze\TelegramLogger\Logging\LogEntry;

final class CompactFormatter implements MessageFormatter
{
    public function format(LogEntry $entry): TelegramText
    {
        return TelegramText::make()
            ->bold($entry->level->getName())
            ->text(' '.$entry->message)
            ->line()
            ->code($entry->exceptions[0]->file ?? '');
    }
}
```

Available segments: `text`, `bold`, `italic`, `code`, `pre`, `quote`, `line`, `append`.

## Diagnostics

```bash
php artisan telegram-logger:test            # the "telegram" channel
php artisan telegram-logger:test ops        # another channel
php artisan telegram-logger:test --queue    # send the test message through the queue
```

The command stops at the first failing check and exits with status 1, so it can run as a deploy health check. The token is never printed in full.

## Migrating from grkamil/laravel-telegram-logging

1. `composer remove grkamil/laravel-telegram-logging && composer require st4rgaze/laravel-telegram-logger`
2. Keep your `.env`: `TELEGRAM_LOGGER_BOT_TOKEN`, `TELEGRAM_LOGGER_CHAT_ID`, `TELEGRAM_LOGGER_MESSAGE_THREAD_ID`, `TELEGRAM_LOGGER_API_HOST`, `TELEGRAM_LOGGER_PROXY` and `TELEGRAM_LOGGER_TIMEOUT` mean the same here.
3. In `config/logging.php` replace `'driver' => 'custom', 'via' => Logger\TelegramLogger::class` with `'driver' => 'telegram'`.
4. Run a queue worker, or set `TELEGRAM_LOGGER_DELIVERY=sync`.
5. Blade templates (`TELEGRAM_LOGGER_TEMPLATE`) are not supported; write a [custom formatter](#custom-formatter) instead.
6. Remove a published `config/telegram-logger.php` from the old package and publish this one.

## Security

The bot token is part of every Bot API URL. This package sends requests from its own HTTP client without Laravel's HTTP events, so instrumentation never records those URLs, and it turns every transport error into an exception that contains neither the URL nor the token. Report vulnerabilities as described in [SECURITY.md](SECURITY.md).

## Testing

```bash
composer test
```

Runs Rector, Pint, PHPStan (level max with strict rules), 100% type coverage and the Pest suite with coverage.

## License

MIT. See [LICENSE.md](LICENSE.md).
