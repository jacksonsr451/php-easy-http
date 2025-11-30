<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server\Support;

use Fiber;
use PhpEasyHttp\Http\Server\Support\EventLoop\EventLoopManager;
use RuntimeException;

final class AsyncDispatcher
{
    public function dispatch(callable $handler): mixed
    {
        return EventLoopManager::run(function () use ($handler) {
            $fiber = new Fiber(static fn () => $handler());
            $result = $fiber->start();

            while ($fiber->isSuspended()) {
                $result = $fiber->resume($result);
            }

            if ($fiber->isTerminated()) {
                return $result;
            }

            throw new RuntimeException('Unable to terminate async fiber.');
        });
    }
}
