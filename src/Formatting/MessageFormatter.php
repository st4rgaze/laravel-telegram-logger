<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Formatting;

use St4rgaze\TelegramLogger\Logging\LogEntry;

interface MessageFormatter
{
    /**
     * Build the Telegram text for an already-redacted log entry.
     */
    public function format(LogEntry $entry): TelegramText;
}
