<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server\Interfaces;

use PhpEasyHttp\Http\Message\Interfaces\ResponseInterface;
use PhpEasyHttp\Http\Message\Interfaces\ServerRequestInterface;

interface RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface;
}
