<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server\Support;

use Closure;
use PhpEasyHttp\Http\Message\Interfaces\ResponseInterface;
use PhpEasyHttp\Http\Message\Interfaces\ServerRequestInterface;
use PhpEasyHttp\Http\Server\Interfaces\RequestHandlerInterface;

final class CallableRequestHandler implements RequestHandlerInterface
{
    public function __construct(private readonly Closure $callback)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return ($this->callback)($request);
    }
}
