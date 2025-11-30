<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server\Support\Async;

interface AwaitableInterface
{
    /**
     * Resolve the underlying asynchronous computation.
     */
    public function execute(): mixed;
}
