<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server;

use PhpEasyHttp\Http\Message\Interfaces\ServerRequestInterface;
use PhpEasyHttp\Http\Server\Exceptions\RouteDontExistException;

class Router
{
    /** @var RouteDefinition[] */
    private array $routes = [];

    public function add(RouteDefinition $definition): void
    {
        $this->routes[] = $definition;
    }

    /**
     * @return array{RouteDefinition, array<string, string>} 
     */
    public function match(ServerRequestInterface $request): array
    {
        $path = $request->getUri()->getPath();
        $method = strtoupper($request->getMethod());

        foreach ($this->routes as $route) {
            $params = $route->match($method, $path);
            if ($params !== null) {
                return [$route, $params];
            }
        }

        throw new RouteDontExistException("Route does not exist: {$method} {$path}");
    }
}
