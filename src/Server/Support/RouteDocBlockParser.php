<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server\Support;

use PhpEasyHttp\Http\Server\Support\Attributes\Route as RouteAttribute;
use PhpEasyHttp\Http\Server\Support\Attributes\RoutePrefix as RoutePrefixAttribute;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;

/**
 * Utility that reads PHPDoc directives from controller classes and methods to
 * build FastAPI-style route definitions without a manual routes file.
 */
final class RouteDocBlockParser
{
    /** @var array<int, string> */
    private array $classTags = [];
    /**
     * Parse every public controller method searching for @Route directives.
     *
     * @return array<int, array{
     *     httpMethod: string,
     *     path: string,
     *     methodName: string,
     *     middleware: array<int, string>,
     *     name: string|null,
     *     summary: string|null,
    *     description?: string|null,
    *     responses?: array<string, mixed>|null,
    *     tags: array<int, string>
     * }>
     */
    public function parse(ReflectionClass $controller): array
    {
        $routes = [];
        $prefix = $this->resolvePrefix($controller);

        foreach ($controller->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor() || $method->isDestructor() || $method->isStatic()) {
                continue;
            }

            foreach ($this->parseMethod($method, $prefix) as $definition) {
                $routes[] = $definition;
            }
        }

        return $routes;
    }

    private function extractPrefix(string $doc): string
    {
        if ($doc === '') {
            return '';
        }

        $directives = $this->extractDirectives($doc);
        foreach (['RoutePrefix', 'Prefix'] as $tag) {
            if (isset($directives[$tag])) {
                $value = $this->extractFirst($directives[$tag]);
                if ($value !== null) {
                    return $value;
                }
            }
        }

        return '';
    }

    private function resolvePrefix(ReflectionClass $controller): string
    {
        $attributes = $controller->getAttributes(RoutePrefixAttribute::class, ReflectionAttribute::IS_INSTANCEOF);
        if ($attributes !== []) {
            $instance = $attributes[0]->newInstance();
            $this->classTags = $instance->getTags();
            return $instance->getPath();
        }

        $this->classTags = [];
        return $this->extractPrefix($controller->getDocComment() ?: '');
    }

    /**
     * @return array<int, array{
     *     httpMethod: string,
     *     path: string,
     *     methodName: string,
     *     middleware: array<int, string>,
     *     name: string|null,
    *     summary: string|null,
    *     description?: string|null,
    *     responses?: array<string, mixed>|null,
    *     tags: array<int, string>
     * }>
     */
    private function parseMethod(ReflectionMethod $method, string $prefix): array
    {
        $docRoutes = $this->parseMethodDocblock($method, $prefix);
        $attrRoutes = $this->parseMethodAttributes($method, $prefix);

        return array_map(fn ($definition) => $this->applyClassTags($definition), array_merge($docRoutes, $attrRoutes));
    }

    /**
     * @return array<int, array{
     *     httpMethod: string,
     *     path: string,
     *     methodName: string,
     *     middleware: array<int, string>,
     *     name: string|null,
    *     summary: string|null,
    *     description?: string|null,
    *     responses?: array<string, mixed>|null,
    *     tags: array<int, string>
     * }>
     */
    private function parseMethodDocblock(ReflectionMethod $method, string $prefix): array
    {
        $doc = $method->getDocComment();
        if ($doc === false) {
            return [];
        }

        $directives = $this->extractDirectives($doc);
        if (! isset($directives['Route'])) {
            return [];
        }

        $middleware = $this->parseList($directives['Middleware'] ?? []);
        $tags = $this->parseList($directives['Tags'] ?? []);
        $name = $this->extractFirst($directives['Name'] ?? null);
        $summary = $this->extractFirst($directives['Summary'] ?? null);
        $description = $this->extractFirst($directives['Description'] ?? null);

        $routes = [];
        foreach ($directives['Route'] as $routeLine) {
            $parsed = $this->parseRouteLine($routeLine);
            if ($parsed === null) {
                continue;
            }

            $fullPath = $this->applyPrefix($prefix, $parsed['path']);
            foreach ($parsed['methods'] as $httpMethod) {
                $routes[] = [
                    'httpMethod' => $httpMethod,
                    'path' => $fullPath,
                    'methodName' => $method->getName(),
                    'middleware' => $middleware,
                    'name' => $name,
                    'summary' => $summary,
                    'description' => $description,
                    'tags' => $tags,
                    'responses' => null,
                ];
            }
        }

        return $routes;
    }

    private function applyClassTags(array $definition): array
    {
        if (! empty($this->classTags)) {
            $definition['tags'] = array_values(array_unique(array_merge($this->classTags, $definition['tags'])));
        }

        return $definition;
    }

    /**
     * @return array<int, array{
     *     httpMethod: string,
     *     path: string,
     *     methodName: string,
     *     middleware: array<int, string>,
     *     name: string|null,
     *     summary: string|null,
     *     tags: array<int, string>
     * }>
     */
    private function parseMethodAttributes(ReflectionMethod $method, string $prefix): array
    {
        $routes = [];
        $attributes = $method->getAttributes(RouteAttribute::class, ReflectionAttribute::IS_INSTANCEOF);

        foreach ($attributes as $attribute) {
            /** @var RouteAttribute $instance */
            $instance = $attribute->newInstance();
            $fullPath = $this->applyPrefix($prefix, $instance->getPath());

            foreach ($instance->getMethods() as $httpMethod) {
                $routes[] = [
                    'httpMethod' => $httpMethod,
                    'path' => $fullPath,
                    'methodName' => $method->getName(),
                    'middleware' => $instance->getMiddleware(),
                    'name' => $instance->getName(),
                    'summary' => $instance->getSummary(),
                    'tags' => $instance->getTags(),
                    'description' => $instance->getDescription(),
                    'responses' => $instance->getResponses(),
                ];
            }
        }

        return $routes;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function extractDirectives(string $doc): array
    {
        $lines = preg_split('/\R/', $doc) ?: [];
        $directives = [];

        foreach ($lines as $line) {
            $line = trim($line);
            $line = ltrim($line, "*\t ");
            if (! str_starts_with($line, '@')) {
                continue;
            }

            $line = substr($line, 1);
            if ($line === false || $line === '') {
                continue;
            }

            $spacePos = strpos($line, ' ');
            $name = $spacePos === false ? $line : substr($line, 0, $spacePos);
            $value = $spacePos === false ? '' : trim(substr($line, $spacePos + 1));

            if ($name === '') {
                continue;
            }

            $directives[$name][] = $value;
        }

        return $directives;
    }

    /**
     * @param array<int, string>|null $values
     */
    private function extractFirst(?array $values): ?string
    {
        if ($values === null || $values === []) {
            return null;
        }

        $value = trim($values[0]);
        return $value === '' ? null : $value;
    }

    /**
     * @param array<int, string> $values
     * @return array<int, string>
     */
    private function parseList(array $values): array
    {
        $result = [];
        foreach ($values as $value) {
            $parts = preg_split('/[|,]/', $value);
            if ($parts === false) {
                continue;
            }

            foreach ($parts as $part) {
                $item = trim($part);
                if ($item !== '') {
                    $result[$item] = $item;
                }
            }
        }

        return array_values($result);
    }

    /**
     * @return array{methods: array<int, string>, path: string}|null
     */
    private function parseRouteLine(string $value): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(?P<methods>[A-Za-z|, ]+)\s+(?P<path>\/[\S]*)$/', $value, $match) === 1) {
            $methods = $this->prepareMethods($match['methods']);
            $path = trim($match['path']);
            if ($methods !== [] && $path !== '') {
                return ['methods' => $methods, 'path' => $path];
            }
        }

        $named = $this->parseNamedArguments($value);
        $methodValue = $named['method'] ?? $named['methods'] ?? null;
        $pathValue = $named['path'] ?? null;

        if ($methodValue !== null && $pathValue !== null) {
            $methods = $this->prepareMethods($methodValue);
            $path = trim($pathValue);
            if ($methods !== [] && $path !== '') {
                return ['methods' => $methods, 'path' => $path];
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function parseNamedArguments(string $value): array
    {
        $arguments = [];
        if (preg_match_all('/(\w+)\s*=\s*("|\')([^"\']*)(\2)/', $value, $matches, PREG_SET_ORDER) === 0) {
                return $arguments;
        }

        foreach ($matches as $match) {
            $key = strtolower($match[1]);
            $arguments[$key] = $match[3];
        }

        return $arguments;
    }

    /**
     * @return array<int, string>
     */
    private function prepareMethods(string $raw): array
    {
        $parts = preg_split('/[|,]/', $raw);
        if ($parts === false) {
            return [];
        }

        $methods = [];
        foreach ($parts as $part) {
            $method = strtoupper(trim($part));
            if ($method === '') {
                continue;
            }

            $methods[$method] = $method;
        }

        return array_values($methods);
    }

    private function applyPrefix(string $prefix, string $path): string
    {
        $normalizedPath = $this->normalizePath($path);
        if ($prefix === '') {
            return $normalizedPath;
        }

        $normalizedPrefix = $this->normalizePath($prefix);
        if ($normalizedPath === '/') {
            return $normalizedPrefix;
        }

        return rtrim($normalizedPrefix, '/') . $normalizedPath;
    }

    private function normalizePath(string $path): string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return '/';
        }

        if ($trimmed[0] !== '/') {
            $trimmed = '/' . $trimmed;
        }

        if ($trimmed !== '/' && str_ends_with($trimmed, '/')) {
            $trimmed = rtrim($trimmed, '/');
        }

        return $trimmed === '' ? '/' : $trimmed;
    }
}
