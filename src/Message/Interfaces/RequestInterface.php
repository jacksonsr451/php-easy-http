<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Message\Interfaces;

interface RequestInterface extends MessageInterface
{
    public function getRequestTarget(): string;

    public function withRequestTarget(string $requestTarget): self;

    public function getMethod(): string;

    public function withMethod(string $method): self;

    public function getUri(): UriInterface;

    public function withUri(UriInterface $uri, bool $preserveHost = false): self;
}
