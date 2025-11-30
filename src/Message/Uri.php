<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Message;

use InvalidArgumentException;
use PhpEasyHttp\Http\Message\Interfaces\UriInterface;

class Uri implements UriInterface
{
    private string $scheme = 'http';

    private string $host = 'localhost';

    private ?int $port = null;

    private string $user = '';

    private ?string $password = null;

    private string $path = '/';

    private string $query = '';

    private string $fragment = '';

    private const SCHEME_PORTS = ['http' => 80, 'https' => 443];
    private const SUPPORTED_SCHEMES = ['http', 'https'];

    public function __construct(string $uri)
    {
        $uriParts = parse_url($uri);
        if ($uriParts === false) {
            throw new InvalidArgumentException('Invalid URI provided.');
        }

        $this->scheme = $this->filterScheme(strtolower($uriParts['scheme'] ?? 'http'));
        $this->host = strtolower($uriParts['host'] ?? 'localhost');
        $this->setPort(isset($uriParts['port']) ? (int) $uriParts['port'] : null);
        $this->user = $uriParts['user'] ?? '';
        $this->password = $uriParts['password'] ?? null;
        $this->path = $this->normalizePath($uriParts['path'] ?? '/');
        $this->query = $uriParts['query'] ?? '';
        $this->fragment = $uriParts['fragment'] ?? '';
    }

    private function normalizePath(string $path): string
    {
        if ($path === '') {
            return '/';
        }

        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        return $path;
    }

    private function filterScheme(string $scheme): string
    {
        if (! in_array($scheme, self::SUPPORTED_SCHEMES, true)) {
            throw new InvalidArgumentException('Unsupported scheme!');
        }

        return $scheme;
    }

    private function setPort(?int $port): void
    {
        if ($port !== null && ($port < 1 || $port > 65535)) {
            throw new InvalidArgumentException('Invalid port provided.');
        }

        $defaultPort = self::SCHEME_PORTS[$this->scheme] ?? null;
        if ($defaultPort !== null && $defaultPort === $port) {
            $this->port = null;
            return;
        }

        $this->port = $port;
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }
    
    public function getAuthority(): string
    {
        $authority = $this->host;
        if ($this->getUserInfo() !== '') {
            $authority = $this->getUserInfo() . '@' . $this->host;
        }

        if ($this->getPort() !== null) {
            $authority .= ':' . $this->getPort();
        }

        return $authority;
    }
    
    public function getUserInfo(): string
    {
        $userInfo = $this->user;
        if ($this->password !== null && ! empty($this->password)) {
            $userInfo .= ':' . $this->password;
        }
        return $userInfo;
    }
    
    public function getHost(): string
    {
        return $this->host;
    }
    
    public function getPort(): null|int
    {
        return $this->port;
    }
    
    public function getPath(): string
    {
        return $this->path;
    }
    
    public function getQuery(): string
    {
        return $this->query;
    }
    
    public function getFragment(): string
    {
        return $this->fragment;
    }
    
    public function withScheme(string $scheme): self
    {
        $normalized = strtolower($scheme);
        if ($this->scheme === $normalized) {
            return $this;
        }

        $clone = clone $this;
        $clone->scheme = $clone->filterScheme($normalized);
        return $clone;
    }
    
    public function withUserInfo(string $user, ?string $password = null): self
    {
        $clone = clone $this;
        $clone->user = $user;
        $clone->password = $password;
        return $clone;
    }
    
    public function withHost(string $host): self
    {
        $normalized = strtolower(trim($host));
        if ($normalized === $this->host) {
            return $this;
        }

        $clone = clone $this;
        $clone->host = $normalized;
        return $clone;
    }
    
    public function withPort(?int $port): self
    {
        if ($this->port === $port) {
            return $this;
        }

        $clone = clone $this;
        $clone->setPort($port);
        return $clone;
    }
    
    public function withPath(string $path): self
    {
        $normalized = $this->normalizePath($path);
        if ($normalized === $this->path) {
            return $this;
        }

        $clone = clone $this;
        $clone->path = $normalized;
        return $clone;
    }
    
    public function withQuery(string $query): self
    {
        $normalized = ltrim($query, '?');
        if ($normalized === $this->query) {
            return $this;
        }

        $clone = clone $this;
        $clone->query = $normalized;
        return $clone;
    }
    
    public function withFragment(string $fragment): self
    {
        $normalized = ltrim($fragment, '#');
        if ($normalized === $this->fragment) {
            return $this;
        }

        $clone = clone $this;
        $clone->fragment = $normalized;
        return $clone;
    }
    
    public function __toString(): string
    {
        $query = '';
        if ($this->query !== '') {
            $query = '?' . $this->query;
        }
        $fragment = '';
        if ($this->fragment !== '') {
            $fragment = '#' . $this->fragment;
        }
        return sprintf(
            '%s://%s%s%s%s',
            $this->scheme,
            $this->getAuthority(),
            $this->getPath(),
            $query,
            $fragment
        );
    }
}
