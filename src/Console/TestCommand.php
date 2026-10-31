<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Console;

use Illuminate\Console\Command;
use St4rgaze\TelegramLogger\Diagnostics\Diagnoser;
use St4rgaze\TelegramLogger\Enums\CheckStatus;

/**
 * @internal
 */
final class TestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram-logger:test
        {channel=telegram : The logging channel to check}
        {--queue : Send the test message through the queue instead of directly}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the Telegram log channel setup and send a test message';

    /**
     * Run the checks and print one line per check.
     */
    public function handle(Diagnoser $diagnoser): int
    {
        $failed = false;

        foreach ($diagnoser->run($this->argument('channel'), $this->option('queue')) as $check) {
            $this->line($check->status->symbol().' '.$check->message);
            $failed = $failed || $check->status === CheckStatus::Fail;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
