<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Message\Interfaces;

interface ResponseInterface extends MessageInterface
{
    public function getStatusCode(): int;

    public function withStatus(int $code, string $reasonPhrase = ''): self;

    public function getReasonPhrase(): string;
}
