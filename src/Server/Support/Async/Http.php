<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server\Support\Async;

use InvalidArgumentException;

final class Http
{
    public static function get(string $url, array $headers = []): AwaitableInterface
    {
        return new Awaitable(static fn (): array => self::request('GET', $url, $headers));
    }

    public static function post(string $url, array $body = [], array $headers = []): AwaitableInterface
    {
        return new Awaitable(static fn (): array => self::request('POST', $url, $headers, $body));
    }

    private static function request(string $method, string $url, array $headers, array $body = []): array
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Invalid URL provided to async HTTP client.');
        }

        $context = [
            'http' => [
                'method' => strtoupper($method),
                'header' => self::formatHeaders($headers),
                'ignore_errors' => true,
            ],
        ];

        if (! empty($body)) {
            $context['http']['content'] = json_encode($body, JSON_THROW_ON_ERROR);
            $context['http']['header'][] = 'Content-Type: application/json';
        }

        $resource = stream_context_create($context);
        $payload = file_get_contents($url, false, $resource);
        $responseHeaders = self::normalizeHeaders($http_response_header ?? []);

        return [
            'status' => self::extractStatus($http_response_header ?? []),
            'headers' => $responseHeaders,
            'body' => $payload,
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function formatHeaders(array $headers): array
    {
        $formatted = [];
        foreach ($headers as $name => $value) {
            $formatted[] = sprintf('%s: %s', $name, $value);
        }

        return $formatted;
    }

    /**
     * @param array<int, string> $headers
     * @return array<string, string>
     */
    private static function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $normalized[strtolower(trim($name))] = trim($value);
        }

        return $normalized;
    }

    /**
     * @param array<int, string> $headers
     */
    private static function extractStatus(array $headers): int
    {
        foreach ($headers as $line) {
            if (preg_match('/^HTTP\/[0-9\.]+\s+(\d{3})/', $line, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return 200;
    }
}
