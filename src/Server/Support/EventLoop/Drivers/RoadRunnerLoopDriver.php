<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server\Support\EventLoop\Drivers;

use PhpEasyHttp\Http\Server\Support\Async\AwaitableInterface;
use PhpEasyHttp\Http\Server\Support\EventLoop\EventLoopDriverInterface;
use Spiral\RoadRunner\Worker;

final class RoadRunnerLoopDriver implements EventLoopDriverInterface
{
    public function __construct(private readonly ?Worker $worker = null)
    {
    }

    public function run(callable $callback): mixed
    {
        return $callback();
    }

    public function await(AwaitableInterface $awaitable): mixed
    {
        return $awaitable->execute();
    }
}
