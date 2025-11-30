<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Message\Interfaces;

interface ServerRequestInterface extends RequestInterface
{
    public function getServerParams(): array;

    public function getCookieParams(): array;

    public function withCookieParams(array $cookies): self;

    public function getQueryParams(): array;

    public function withQueryParams(array $query): self;

    public function getUploadedFiles(): array;

    public function withUploadedFiles(array $uploadedFiles): self;

    public function getParsedBody(): array|object|null;

    public function withParsedBody(array|object|null $data): self;

    public function getAttributes(): array;

    public function getAttribute(string $name, mixed $default = null): mixed;

    public function withAttribute(string $name, mixed $value): self;

    public function withoutAttribute(string $name): self;
}
