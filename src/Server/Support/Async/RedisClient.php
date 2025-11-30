<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server\Support\Async;

final class RedisClient
{
    /** @var array<string, mixed> */
    private static array $store = [];

    public static function get(string $key): AwaitableInterface
    {
        return new Awaitable(static fn () => self::$store[$key] ?? null);
    }

    public static function set(string $key, mixed $value): AwaitableInterface
    {
        return new Awaitable(static function () use ($key, $value): bool {
            self::$store[$key] = $value;
            return true;
        });
    }
}
