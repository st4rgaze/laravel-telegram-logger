<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Enums;

/**
 * @internal
 */
enum Delivery: string
{
    case Queue = 'queue';
    case Sync = 'sync';
}
