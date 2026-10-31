<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Formatting;

use Illuminate\Contracts\Container\Container;
use St4rgaze\TelegramLogger\Delivery\OutgoingMessage;
use St4rgaze\TelegramLogger\Delivery\TraceFile;
use St4rgaze\TelegramLogger\Logging\ChannelConfig;
use St4rgaze\TelegramLogger\Logging\LogEntry;
use St4rgaze\TelegramLogger\Telegram\SecretMasker;

/**
 * @internal
 */
final readonly class MessageComposer
{
    /**
     * Create a composer.
     */
    public function __construct(
        private Container $app,
        private MessageSplitter $splitter,
        private TraceFileBuilder $traces,
    ) {}

    /**
     * Turn log entries into a message ready for delivery, with the bot token masked everywhere.
     *
     * @param  non-empty-list<LogEntry>  $entries
     */
    public function compose(array $entries, ChannelConfig $config, string $fingerprint): OutgoingMessage
    {
        /** @var MessageFormatter $formatter */
        $formatter = $this->app->make($config->formatter);
        $text = TelegramText::make();
        $silent = true;

        foreach ($entries as $index => $entry) {
            if ($index > 0) {
                $text->line()->text('──────────')->line();
            }

            $text->append($formatter->format($entry));
            $silent = $silent && $entry->level->isLowerThan($config->silentBelow);
        }

        $text = $this->mask($text, $config);
        $split = $this->splitter->split($text, $config->maxChunks);
        $caption = strtok($text->plainText(), "\n");
        $trace = $this->traces->build($entries, $split->overflow, $config, $fingerprint, $caption === false ? '' : $caption);

        return new OutgoingMessage(
            $split->chunks,
            $trace instanceof TraceFile ? new TraceFile(
                $trace->filename,
                SecretMasker::mask($trace->contents, $config->token),
                SecretMasker::mask($trace->caption, $config->token),
            ) : null,
            $silent,
        );
    }

    /**
     * Mask the bot token in every segment before the text is split, so no chunk boundary can cut it in half.
     */
    private function mask(TelegramText $text, ChannelConfig $config): TelegramText
    {
        $masked = TelegramText::make();

        foreach ($text->segments() as $segment) {
            $masked->segment(SecretMasker::mask($segment->text, $config->token), $segment->style);
        }

        return $masked;
    }
}
