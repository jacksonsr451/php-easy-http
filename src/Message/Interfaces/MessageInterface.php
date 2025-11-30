<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Message\Interfaces;

interface MessageInterface
{
    public function getProtocolVersion(): string;

    public function withProtocolVersion(string $version): self;

    public function getHeaders(): array;

    public function hasHeader(string $name): bool;

    public function getHeader(string $name): array;

    public function getHeaderLine(string $name): string;

    public function withHeader(string $name, string|array $value): self;

    public function withAddedHeader(string $name, string|array $value): self;

    public function withoutHeader(string $name): self;

    public function getBody(): StreamInterface;

    public function withBody(StreamInterface $body): self;

    public function setHeaders(array $headers): void;

    public function setBody(mixed $body): void;
}
