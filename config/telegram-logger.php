<?php

declare(strict_types=1);

use St4rgaze\TelegramLogger\Formatting\DefaultMessageFormatter;

return [

    /*
    |--------------------------------------------------------------------------
    | Bot Token & Chat
    |--------------------------------------------------------------------------
    |
    | The bot token from @BotFather and the chat that receives the logs. A
    | group, supergroup, channel or private chat id all work. When either
    | value is blank the channel silently does nothing; run the command
    | "php artisan telegram-logger:test" to find out what is missing.
    |
    */

    'token' => env('TELEGRAM_LOGGER_BOT_TOKEN'),

    'chat_id' => env('TELEGRAM_LOGGER_CHAT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Forum Topic
    |--------------------------------------------------------------------------
    |
    | When the chat is a forum supergroup, the id of the topic (message
    | thread) the logs should be posted in. Leave empty for normal chats.
    |
    */

    'message_thread_id' => env('TELEGRAM_LOGGER_MESSAGE_THREAD_ID'),

    /*
    |--------------------------------------------------------------------------
    | API Host, Proxy & Timeout
    |--------------------------------------------------------------------------
    |
    | The Bot API host must use https, unless it is a local Bot API server
    | on localhost. The timeout is the total request time in seconds; the
    | connection timeout is fixed at two seconds.
    |
    */

    'api_host' => env('TELEGRAM_LOGGER_API_HOST', 'https://api.telegram.org'),

    'proxy' => env('TELEGRAM_LOGGER_PROXY'),

    'timeout' => env('TELEGRAM_LOGGER_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | Delivery
    |--------------------------------------------------------------------------
    |
    | "queue" sends messages from a queued job so a slow or unreachable
    | Telegram never delays your requests. "sync" sends them immediately
    | and is meant for applications without a queue worker.
    |
    */

    'delivery' => env('TELEGRAM_LOGGER_DELIVERY', 'queue'),

    'queue' => [
        'connection' => env('TELEGRAM_LOGGER_QUEUE_CONNECTION'),
        'name' => env('TELEGRAM_LOGGER_QUEUE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Throttling
    |--------------------------------------------------------------------------
    |
    | Identical records are sent once per dedupe window, and each chat
    | receives at most "max_per_minute" messages (Telegram limits groups
    | to about 20 per minute). Dropped records are counted and reported
    | on the next message that gets through.
    |
    */

    'throttle' => [
        'enabled' => true,
        'dedupe_seconds' => 60,
        'max_per_minute' => 20,
        'cache_store' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redaction
    |--------------------------------------------------------------------------
    |
    | Context and extra keys (matched case-insensitively, at any depth) whose
    | values are replaced before anything leaves your server. Route parameters
    | whose names contain one of these keys (e.g. {token}, {invite_token}) are
    | shown as placeholders in the request URL. The patterns
    | are regular expressions masked in every text: the message, context
    | values, exception messages and stack traces. Email addresses are
    | masked by default; add your own, e.g. '/\b08\d{8,11}\b/' for
    | Indonesian mobile numbers.
    |
    */

    'redact' => [
        'keys' => [
            'password',
            'password_confirmation',
            'current_password',
            'new_password',
            '_token',
            'x-api-key',
            'token',
            'secret',
            'authorization',
            'cookie',
            'api_key',
            'access_token',
            'refresh_token',
            'credit_card',
            'cvv',
            'hash',
            'signature',
        ],
        'patterns' => [
            '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
        ],
        'replacement' => '[redacted]',
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Context
    |--------------------------------------------------------------------------
    |
    | Details of the current HTTP request added to each message. The IP
    | address is personal data in many jurisdictions and is off by default.
    |
    */

    'request_context' => [
        'url' => true,
        'method' => true,
        'route' => true,
        'user_id' => true,
        'ip' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Stack Trace File
    |--------------------------------------------------------------------------
    |
    | When a record carries an exception, the full stack trace is attached
    | as a text file. Text that does not fit in "max_chunks" messages is
    | moved into this file as well.
    |
    */

    'trace' => [
        'attach' => true,
        'max_bytes' => 200_000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Limits & Notifications
    |--------------------------------------------------------------------------
    |
    | Long records are split into at most "max_chunks" messages. Records
    | below the "silent_below" level are delivered without a notification
    | sound.
    |
    */

    'max_chunks' => 3,

    'silent_below' => 'error',

    /*
    |--------------------------------------------------------------------------
    | Formatter
    |--------------------------------------------------------------------------
    |
    | The class that turns a log entry into Telegram text. It must
    | implement St4rgaze\TelegramLogger\Formatting\MessageFormatter.
    |
    */

    'formatter' => DefaultMessageFormatter::class,

    /*
    |--------------------------------------------------------------------------
    | Fallback Channel
    |--------------------------------------------------------------------------
    |
    | The log channel that receives a short, token-free note when a message
    | cannot be delivered. When null, PHP's error_log() is used.
    |
    */

    'fallback_channel' => null,

];
