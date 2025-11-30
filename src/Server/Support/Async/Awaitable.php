<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server\Support\Async;

use Closure;

final class Awaitable implements AwaitableInterface
{
    private Closure $resolver;

    /**
     * @param Closure():mixed $resolver
     */
    public function __construct(Closure $resolver)
    {
        $this->resolver = $resolver;
    }

    public static function from(callable $resolver): self
    {
        return new self(\Closure::fromCallable($resolver));
    }

    public function execute(): mixed
    {
        return ($this->resolver)();
    }
}
