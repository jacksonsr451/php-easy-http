<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Message\Traits;

use InvalidArgumentException;
use PhpEasyHttp\Http\Message\Interfaces\StreamInterface;
use PhpEasyHttp\Http\Message\Stream;

trait MessageTrait
{
    private string $protocol = '1.1';

    /** @var array<string, string[]> */
    private array $headers = [];

    private ?StreamInterface $body = null;

    public function getProtocolVersion(): string
    {
        return $this->protocol;
    }

    public function withProtocolVersion(string $version): self
    {
        if ($this->protocol === $version) {
            return $this;
        }

        $clone = clone $this;
        $clone->protocol = $version;
        return $clone;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        $name = strtolower($name);
        return array_key_exists($name, $this->headers);
    }

    public function getHeader(string $name): array
    {
        $name = strtolower($name);
        return $this->headers[$name] ?? [];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(',', $this->getHeader($name));
    }

    public function withHeader(string $name, string|array $value): self
    {
        if ($name === '') {
            throw new InvalidArgumentException('Header name cannot be empty.');
        }

        $name = strtolower($name);
        $values = is_array($value)
            ? array_values(array_map(static fn ($headerValue): string => trim((string) $headerValue), $value))
            : [trim((string) $value)];

        $clone = clone $this;
        $clone->headers[$name] = $values;
        return $clone;
    }

    public function withAddedHeader(string $name, string|array $value): self
    {
        if ($name === '') {
            throw new InvalidArgumentException('Header name cannot be empty.');
        }

        $name = strtolower($name);
        $values = is_array($value)
            ? array_values(array_map(static fn ($headerValue): string => trim((string) $headerValue), $value))
            : [trim((string) $value)];

        $clone = clone $this;
        $clone->headers[$name] = array_merge($clone->headers[$name] ?? [], $values);
        return $clone;
    }

    public function withoutHeader(string $name): self
    {
        $name = strtolower($name);
        if (! $this->hasHeader($name)) {
            return $this;
        }

        $clone = clone $this;
        unset($clone->headers[$name]);
        return $clone;
    }

    public function getBody(): StreamInterface
    {
        return $this->body ??= new Stream();
    }

    public function withBody(StreamInterface $body): self
    {
        $clone = clone $this;
        $clone->body = $body;
        return $clone;
    }

    public function setHeaders(array $headers): void
    {
        foreach ($headers as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $normalized = strtolower($key);
            if (is_string($value)) {
                $this->headers[$normalized] = array_map('trim', explode(',', $value));
                continue;
            }

            if (is_array($value)) {
                $this->headers[$normalized] = array_values(array_map(static fn ($headerValue): string => trim((string) $headerValue), $value));
            }
        }
    }

    public function setBody(mixed $body): void
    {
        if (! $body instanceof StreamInterface) {
            $body = new Stream($body);
        }

        $this->body = $body;
    }

    protected function inHeader(string $name, string $value): bool
    {
        $headers = $this->getHeader($name);
        foreach ($headers as $headerValue) {
            if (stripos($headerValue, $value) === 0) {
                return true;
            }
        }

        return false;
    }
}
