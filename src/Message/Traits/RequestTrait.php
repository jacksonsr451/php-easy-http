<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Message\Traits;

use InvalidArgumentException;
use PhpEasyHttp\Http\Message\Interfaces\UriInterface;
use PhpEasyHttp\Http\Message\Uri;

trait RequestTrait
{
    protected string $requestTarget = '/';

    protected string $method = 'get';

    protected ?UriInterface $uri = null;

    /** @var string[] */
    protected array $validMethods = [
        'post',
        'get',
        'delete',
        'put',
        'patch',
        'head',
        'options',
    ];

    public function getRequestTarget(): string
    {
        return $this->requestTarget;
    }

    public function withRequestTarget(string $requestTarget): self
    {
        if ($this->requestTarget === $requestTarget) {
            return $this;
        }

        $clone = clone $this;
        $clone->requestTarget = $requestTarget;
        return $clone;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod(string $method): self
    {
        $normalized = strtolower($method);
        if ($this->method === $normalized) {
            return $this;
        }

        if (! in_array($normalized, $this->validMethods, true)) {
            throw new InvalidArgumentException('Only ' . implode(', ', $this->validMethods) . ' are acceptable');
        }

        $clone = clone $this;
        $clone->method = $normalized;
        return $clone;
    }

    public function getUri(): UriInterface
    {
        if ($this->uri === null) {
            throw new InvalidArgumentException('URI has not been defined.');
        }

        return $this->uri;
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): self
    {
        $clone = clone $this;

        if ($preserveHost && $clone->uri !== null && $clone->uri->getHost() !== '') {
            $uri = $uri->withHost($clone->uri->getHost());
        }

        $clone->uri = $uri;
        return $clone;
    }

    private function setUri(UriInterface|string $uri): void
    {
        if (is_string($uri)) {
            $uri = new Uri($uri);
        }

        $this->uri = $uri;
    }
}
