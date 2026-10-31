<?php

declare(strict_types=1);

namespace St4rgaze\TelegramLogger\Context;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use St4rgaze\TelegramLogger\Logging\ChannelConfig;
use Throwable;

/**
 * @internal
 */
final readonly class RequestContextResolver
{
    /**
     * Create a resolver for the current application.
     */
    public function __construct(private Application $app) {}

    /**
     * Get the enabled details of the current HTTP request, or nothing outside a request.
     *
     * @return array<string, string>
     */
    public function resolve(ChannelConfig $config): array
    {
        if (! $this->app->bound('request')) {
            return [];
        }

        $request = $this->app->make('request');

        $route = $request->route();

        if ($this->app->runningInConsole() && ! $route instanceof Route) {
            return [];
        }

        $enabled = $config->requestContext;

        return array_filter([
            'url' => $enabled['url'] ? $this->url($request, $route, $config->redactKeys) : null,
            'method' => $enabled['method'] ? $request->method() : null,
            'route' => $enabled['route'] && $route instanceof Route ? ($route->getName() ?? $route->uri()) : null,
            'user_id' => $enabled['user_id'] ? $this->userId($request) : null,
            'ip' => $enabled['ip'] ? $request->ip() : null,
        ], fn (?string $value): bool => $value !== null && $value !== '');
    }

    /**
     * Get the request path, keeping route parameters whose names look secret (reset tokens, signatures) as placeholders.
     *
     * @param  list<string>  $secretNames
     */
    private function url(Request $request, mixed $route, array $secretNames): string
    {
        if (! $route instanceof Route) {
            return $request->url();
        }

        $path = preg_replace_callback(
            '/\{(\w+)\??\}/',
            fn (array $match): string => $this->isSecret($match[1], $secretNames)
                ? '{'.$match[1].'}'
                : $this->parameter($route, $match[1]),
            $route->uri(),
        ) ?? $route->uri();

        $path = mb_trim((string) preg_replace('#/+#', '/', $path), '/');

        return $path === '' ? $request->root() : $request->root().'/'.$path;
    }

    /**
     * Determine whether a route parameter name contains one of the redacted key names.
     *
     * @param  list<string>  $secretNames
     */
    private function isSecret(string $name, array $secretNames): bool
    {
        $name = mb_strtolower($name);

        return array_any($secretNames, fn (string $secretName): bool => str_contains($name, mb_strtolower($secretName)));
    }

    /**
     * Get a route parameter's value as it appeared in the path.
     */
    private function parameter(Route $route, string $name): string
    {
        return $route->originalParameter($name) ?? '';
    }

    /**
     * Get the authenticated user's identifier, if any.
     */
    private function userId(Request $request): ?string
    {
        try {
            $identifier = $request->user()?->getAuthIdentifier();
        } catch (Throwable) {
            return null;
        }

        return is_scalar($identifier) ? (string) $identifier : null;
    }
}
