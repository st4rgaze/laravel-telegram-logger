<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Telegram;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use St4rgaze\TelegramLogger\Delivery\TraceFile;
use St4rgaze\TelegramLogger\Logging\ChannelConfig;
use Throwable;

/**
 * @internal
 */
final readonly class TelegramClient
{
    /**
     * Create a client on an HTTP factory that has no event dispatcher, so request URLs never reach HTTP instrumentation.
     */
    public function __construct(private Factory $http) {}

    /**
     * Send an HTML message.
     *
     * @throws TelegramApiException
     */
    public function sendMessage(ChannelConfig $config, string $html, bool $silent): TelegramResponse
    {
        return $this->call($config, fn (PendingRequest $request): Response => $request->post(
            $this->path($config, 'sendMessage'),
            array_filter([
                'chat_id' => $config->chatId,
                'message_thread_id' => $config->messageThreadId,
                'text' => $html,
                'parse_mode' => 'HTML',
                'disable_notification' => $silent,
                'link_preview_options' => ['is_disabled' => true],
            ], fn (mixed $value): bool => $value !== null),
        ));
    }

    /**
     * Upload a text file as a document.
     *
     * @throws TelegramApiException
     */
    public function sendDocument(ChannelConfig $config, TraceFile $file, bool $silent): TelegramResponse
    {
        return $this->call($config, fn (PendingRequest $request): Response => $request
            ->attach('document', $file->contents, $file->filename)
            ->post($this->path($config, 'sendDocument'), array_filter([
                'chat_id' => $config->chatId,
                'message_thread_id' => $config->messageThreadId === null ? null : (string) $config->messageThreadId,
                'caption' => $file->caption,
                'disable_notification' => $silent ? 'true' : 'false',
            ], fn (?string $value): bool => $value !== null)));
    }

    /**
     * Get the bot's own user.
     *
     * @throws TelegramApiException
     */
    public function getMe(ChannelConfig $config): TelegramResponse
    {
        return $this->call($config, fn (PendingRequest $request): Response => $request->post($this->path($config, 'getMe')));
    }

    /**
     * Get the configured chat.
     *
     * @throws TelegramApiException
     */
    public function getChat(ChannelConfig $config): TelegramResponse
    {
        return $this->call($config, fn (PendingRequest $request): Response => $request->post(
            $this->path($config, 'getChat'),
            ['chat_id' => $config->chatId],
        ));
    }

    /**
     * Run a Bot API call and convert every failure into a token-free exception.
     *
     * @param  callable(PendingRequest): Response  $send
     *
     * @throws TelegramApiException
     */
    private function call(ChannelConfig $config, callable $send): TelegramResponse
    {
        try {
            $response = $send($this->request($config));
        } catch (Throwable) {
            throw TelegramApiException::connectionFailed();
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw TelegramApiException::invalidResponse($response->status());
        }

        if ($response->successful() && ($body['ok'] ?? false) === true) {
            $result = $body['result'] ?? [];

            return new TelegramResponse(is_array($result) ? $result : []);
        }

        $description = $body['description'] ?? null;
        $retryAfter = is_array($body['parameters'] ?? null) ? ($body['parameters']['retry_after'] ?? null) : null;

        throw TelegramApiException::fromResponse(
            $response->status(),
            is_string($description) ? $description : null,
            is_int($retryAfter) ? $retryAfter : null,
            $config->token,
        );
    }

    /**
     * Build a request with the channel's host, timeouts and proxy.
     */
    private function request(ChannelConfig $config): PendingRequest
    {
        return $this->http
            ->createPendingRequest()
            ->baseUrl($config->apiHost)
            ->connectTimeout(2)
            ->timeout($config->timeout)
            ->withOptions($config->proxy === null ? [] : ['proxy' => $config->proxy]);
    }

    /**
     * Get the method path, which carries the bot token as the Bot API requires.
     */
    private function path(ChannelConfig $config, string $method): string
    {
        return sprintf('/bot%s/%s', $config->token, $method);
    }
}
