<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server\Support\EventLoop\Drivers;

use PhpEasyHttp\Http\Server\Support\Async\AwaitableInterface;
use PhpEasyHttp\Http\Server\Support\EventLoop\EventLoopDriverInterface;

final class SyncLoopDriver implements EventLoopDriverInterface
{
    public function run(callable $callback): mixed
    {
        return $callback();
    }

    public function await(AwaitableInterface $awaitable): mixed
    {
        return $awaitable->execute();
    }
}
