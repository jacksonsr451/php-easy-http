<?php

declare(strict_types=1);

use PhpEasyHttp\Http\Server\Support\Async\AwaitableInterface;
use PhpEasyHttp\Http\Server\Support\EventLoop\EventLoopManager;

if (! function_exists('await')) {
    /**
     * Await the result of an async-aware operation.
     */
    function await(mixed $value): mixed
    {
        if ($value instanceof AwaitableInterface) {
            return EventLoopManager::driver()->await($value);
        }

        return $value;
    }
}
