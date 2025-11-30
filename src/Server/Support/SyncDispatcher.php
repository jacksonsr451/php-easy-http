<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server\Support;

final class SyncDispatcher
{
    public function dispatch(callable $handler): mixed
    {
        return $handler();
    }
}
