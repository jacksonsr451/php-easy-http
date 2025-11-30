<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server\Support\EventLoop\Drivers;

use Amp\Loop;
use PhpEasyHttp\Http\Server\Support\Async\AwaitableInterface;
use PhpEasyHttp\Http\Server\Support\EventLoop\EventLoopDriverInterface;

final class AmpLoopDriver implements EventLoopDriverInterface
{
    public function run(callable $callback): mixed
    {
        $result = null;
        Loop::run(static function () use ($callback, &$result): void {
            $result = $callback();
            Loop::stop();
        });

        return $result;
    }

    public function await(AwaitableInterface $awaitable): mixed
    {
        return $awaitable->execute();
    }
}
