<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server;

use PhpEasyHttp\Http\Message\Interfaces\ServerRequestInterface;
use PhpEasyHttp\Http\Server\Exceptions\RouteDontExistException;
use PhpEasyHttp\Http\Server\Support\AsyncDetector;

class Router
{
    /** @var RouteDefinition[] */
    private array $routes = [];

    public function add(RouteDefinition $definition): void
    {
        $this->routes[] = $definition;
    }

    public function clear(): void
    {
        $this->routes = [];
    }

    /**
     * @return RouteDefinition[]
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * @return array{RouteDefinition, array<string, string>, bool}
     */
    public function match(ServerRequestInterface $request): array
    {
        $path = $request->getUri()->getPath();
        $method = strtoupper($request->getMethod());

        foreach ($this->routes as $route) {
            $params = $route->match($method, $path);
            if ($params !== null) {
                $isAsync = AsyncDetector::isAsync($route->getHandler());
                return [$route, $params, $isAsync];
            }
        }

        throw new RouteDontExistException("Route does not exist: {$method} {$path}");
    }
}
