<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server\Support\EventLoop\Drivers;

use PhpEasyHttp\Http\Server\Support\Async\AwaitableInterface;
use PhpEasyHttp\Http\Server\Support\EventLoop\EventLoopDriverInterface;
use Swoole\Coroutine;

final class SwooleLoopDriver implements EventLoopDriverInterface
{
    public function run(callable $callback): mixed
    {
        $result = null;
        Coroutine::run(static function () use ($callback, &$result): void {
            $result = $callback();
        });

        return $result;
    }

    public function await(AwaitableInterface $awaitable): mixed
    {
        return $awaitable->execute();
    }
}
