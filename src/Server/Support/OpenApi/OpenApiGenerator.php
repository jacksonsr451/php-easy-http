<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server\Support\OpenApi;

use PhpEasyHttp\Http\Server\RouteDefinition;
use PhpEasyHttp\Http\Server\Router;

/**
 * Generates a minimal OpenAPI 3.1 specification directly from the registered routes.
 */
final class OpenApiGenerator
{
    /**
     * @param array{
     *     title?: string,
     *     version?: string,
     *     description?: string|null,
     *     servers?: array<int, array<string, mixed>>
     * } $config
     */
    public function __construct(private readonly Router $router, private readonly array $config = [])
    {
    }

    public function generate(): array
    {
        $info = array_filter([
            'title' => $this->config['title'] ?? 'php-easy-http API',
            'version' => $this->config['version'] ?? '1.0.0',
            'description' => $this->config['description'] ?? null,
        ], static fn ($value) => $value !== null);

        $paths = [];
        foreach ($this->router->getRoutes() as $route) {
            $path = $route->getPath();
            $method = strtolower($route->getMethod());

            $paths[$path][$method] = array_filter([
                'operationId' => $route->getName() ?? $this->fallbackOperationId($route),
                'summary' => $route->getSummary(),
                'description' => $route->getDescription(),
                'tags' => $route->getTags() ?: null,
                'parameters' => $this->buildParameters($route),
                'responses' => $route->getResponses() ?? [
                    '200' => [
                        'description' => 'Successful response',
                    ],
                ],
            ], static fn ($value) => $value !== null && $value !== []);
        }

        return array_filter([
            'openapi' => '3.1.0',
            'info' => $info,
            'servers' => $this->config['servers'] ?? null,
            'paths' => $this->normalizePaths($paths),
        ], static fn ($value) => $value !== null);
    }

    private function fallbackOperationId(RouteDefinition $route): string
    {
        $path = trim($route->getPath(), '/');
        $normalizedPath = $path === '' ? 'root' : str_replace(['/', '{', '}', '-'], ['_', '', '', '_'], $path);
        return strtolower($route->getMethod()) . '_' . $normalizedPath;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildParameters(RouteDefinition $route): array
    {
        $parameters = [];
        foreach ($route->getParameterNames() as $name) {
            $parameters[] = [
                'name' => $name,
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'string'],
            ];
        }

        return $parameters;
    }

    private function normalizePaths(array $paths): array
    {
        $normalized = [];
        foreach ($paths as $path => $operations) {
            $normalized[$path] = $operations;
        }

        return $normalized;
    }
}
