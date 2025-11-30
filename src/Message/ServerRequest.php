<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Message;

use InvalidArgumentException;
use PhpEasyHttp\Http\Message\Interfaces\ServerRequestInterface;
use PhpEasyHttp\Http\Message\Interfaces\UploadFileInterface;
use PhpEasyHttp\Http\Message\Interfaces\UriInterface;
use PhpEasyHttp\Http\Message\Traits\MessageTrait;
use PhpEasyHttp\Http\Message\Traits\RequestTrait;

class ServerRequest implements ServerRequestInterface
{
    use MessageTrait;
    use RequestTrait;

    private array $servers = [];
    private array $cookies = [];
    private array $queries = [];
    private array $uploadFiles = [];
    private array|object|null $parsedBody = null;
    private array $attributes = [];

    public function __construct(
        string $method,
        UriInterface|string $uri,
        array $headers = [],
        array $servers = [],
        array $cookies = [],
        array $attributes = [],
        mixed $body = null,
        string $version = '1.1'
    ) {
        $this->method = strtolower($method);
        if (! in_array($this->method, $this->validMethods, true)) {
            throw new InvalidArgumentException('Unsupported HTTP method provided.');
        }
        $this->protocol = $version;
        $this->servers = $servers;
        $this->cookies = $cookies;
        $this->attributes = $attributes;
        $this->setUri($uri);
        $this->setHeaders($headers);
        $this->setBody($body);
    }

    public function getServerParams(): array
    {
        return $this->servers;
    }

    public function getCookieParams(): array
    {
        return $this->cookies;
    }

    public function withCookieParams(array $cookies): self
    {
        $clone = clone $this;
        $clone->cookies = $cookies;
        return $clone;
    }

    public function getQueryParams(): array
    {
        if (! empty($this->queries)) {
            return $this->queries;
        }

        $queries = [];
        parse_str($this->getUri()->getQuery(), $queries);
        $this->queries = $queries;
        return $this->queries;
    }

    public function withQueryParams(array $query): self
    {
        $clone = clone $this;
        $clone->queries = $query;
        return $clone;
    }

    public function getUploadedFiles(): array
    {
        return $this->uploadFiles;
    }

    public function withUploadedFiles(array $uploadedFiles): self
    {
        foreach ($uploadedFiles as $file) {
            if (! $file instanceof UploadFileInterface) {
                throw new InvalidArgumentException('Uploaded files must implement UploadFileInterface.');
            }
        }

        $clone = clone $this;
        $clone->uploadFiles = $uploadedFiles;
        return $clone;
    }

    public function getParsedBody(): array|object|null
    {
        if ($this->parsedBody !== null) {
            return $this->parsedBody;
        }

        if ($this->inPost()) {
            return $this->parsedBody = $_POST;
        }

        if ($this->inHeader('content-type', 'application/json')) {
            $decoded = json_decode((string) $this->getBody(), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $this->parsedBody = $decoded;
            }
        }

        return null;
    }

    public function inPost(): bool
    {
        $postHeaders = ['application/x-www-form-urlencoded', 'multipart/form-data'];
        $headersValues = $this->getHeader('content-type');
        foreach ($headersValues as $value) {
            if (in_array(strtolower($value), $postHeaders, true)) {
                return true;
            }
        }
        return false;
    }

    public function withParsedBody(array|object|null $data): self
    {
        $clone = clone $this;
        $clone->parsedBody = $data;
        return $clone;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute(string $name, mixed $value): self
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;
        return $clone;
    }

    public function withoutAttribute(string $name): self
    {
        $clone = clone $this;
        unset($clone->attributes[$name]);
        return $clone;
    }
}
