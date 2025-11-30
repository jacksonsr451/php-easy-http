<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server\Support\EventLoop;

use PhpEasyHttp\Http\Server\Support\Async\AwaitableInterface;

interface EventLoopDriverInterface
{
    public function run(callable $callback): mixed;

    public function await(AwaitableInterface $awaitable): mixed;
}
