<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server;

use Closure;
use PhpEasyHttp\Http\Server\Exceptions\RouteDontExistException;

class RouteDefinition
{
    /** @var string[] */
    private array $parameterNames = [];

    private string $regex;

    private Closure $handler;

    /**
     * @param callable $handler Route callable executed when the route matches.
     * @param array<string> $middleware List of middleware identifiers attached to the route.
     * @param array<string> $tags Optional tags used for documentation filtering.
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        callable $handler,
        private readonly array $middleware = [],
        private readonly ?string $name = null,
        private readonly ?string $summary = null,
        private readonly array $tags = []
    ) {
        if ($path === '' || $path[0] !== '/') {
            throw new RouteDontExistException('Route paths must start with a forward slash.');
        }

        $this->handler = $handler instanceof Closure ? $handler : Closure::fromCallable($handler);
        $this->compilePath($path);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getHandler(): Closure
    {
        return $this->handler;
    }

    /**
     * @return array<string>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    /**
     * @return array<string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * Attempt to match the route against the provided HTTP method and path.
     *
     * @return array<string, string>|null Returns the extracted parameters when it matches, null otherwise.
     */
    public function match(string $method, string $path): ?array
    {
        if (strtoupper($method) !== $this->method) {
            return null;
        }

        if (! preg_match($this->regex, $path, $matches)) {
            return null;
        }

        $params = [];
        foreach ($this->parameterNames as $parameter) {
            $params[$parameter] = $matches[$parameter] ?? null;
        }

        return $params;
    }

    private function compilePath(string $path): void
    {
        $expression = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_-]*)\}/',
            function (array $matches): string {
                $this->parameterNames[] = $matches[1];
                return '(?P<' . $matches[1] . '>[^/]+)';
            },
            $path
        );

        if ($expression === null) {
            throw new RouteDontExistException('Unable to compile route expression.');
        }

        $this->regex = '#^' . rtrim($expression, '/') . '/?$#';
    }
}
