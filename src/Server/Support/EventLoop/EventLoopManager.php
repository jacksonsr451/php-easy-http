<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server\Support\EventLoop;

use PhpEasyHttp\Http\Server\Support\EventLoop\Drivers\AmpLoopDriver;
use PhpEasyHttp\Http\Server\Support\EventLoop\Drivers\RoadRunnerLoopDriver;
use PhpEasyHttp\Http\Server\Support\EventLoop\Drivers\SyncLoopDriver;
use PhpEasyHttp\Http\Server\Support\EventLoop\Drivers\SwooleLoopDriver;

final class EventLoopManager
{
    private static ?EventLoopDriverInterface $driver = null;

    public static function driver(): EventLoopDriverInterface
    {
        return self::$driver ??= self::detectDriver();
    }

    public static function useDriver(EventLoopDriverInterface $driver): void
    {
        self::$driver = $driver;
    }

    public static function run(callable $callback): mixed
    {
        return self::driver()->run($callback);
    }

    private static function detectDriver(): EventLoopDriverInterface
    {
        if (extension_loaded('swoole') && class_exists('\Swoole\\Coroutine')) {
            return new SwooleLoopDriver();
        }

        if (class_exists('Spiral\\RoadRunner\\Worker')) {
            return new RoadRunnerLoopDriver();
        }

        if (class_exists('Amp\\Loop')) {
            return new AmpLoopDriver();
        }

        return new SyncLoopDriver();
    }
}
