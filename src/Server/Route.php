<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server;

use PhpEasyHttp\Http\Message\Interfaces\ServerRequestInterface;
use PhpEasyHttp\Http\Message\Interfaces\UriInterface;
use PhpEasyHttp\Http\Message\ServerRequest;
use PhpEasyHttp\Http\Message\Uri;
use PhpEasyHttp\Http\Server\Exceptions\ClassDontExistException;
use PhpEasyHttp\Http\Server\Exceptions\MethodDontExistException;
use PhpEasyHttp\Http\Server\Exceptions\RouteDontExistException;
use Throwable;

class Route
{
    private static array $routes = [];

    private static array $params = [];

    public static function load(): void
    {
        $path = self::getUri()->getPath();
        $request = self::getRequest();

        try {
            $route = self::$routes[$path] ?? null;
            if ($route === null) {
                $matched = self::validateUriWithParams($path);
                if (empty($matched)) {
                    throw new RouteDontExistException("Route does not exist: {$path}");
                }
                $route = array_shift($matched);
            } else {
                self::$params = [];
            }

            $controllerClass = self::getController($route);
            $controller = new $controllerClass();
            $action = self::getMethod($controller, $route);

            (new RequestHandler(
                $route['middlewares'] ?? [],
                function () use ($controller, $action, $request): void {
                    self::loadMethod($controller, $action, $request);
                },
                []
            ))->handle($request);
        } catch (Throwable $ex) {
            echo '<pre>' . $ex->getMessage() . '</pre>';
        }
    }

    private static function getUri(): UriInterface
    {
        $port = (int) ($_SERVER['SERVER_PORT'] ?? 80);
        $scheme = $port === 443 ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';

        return new Uri(sprintf('%s://%s%s', $scheme, $host, $requestUri));
    }

    private static function getRequest(): ServerRequestInterface
    {
        return new ServerRequest(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            self::getUri(),
            self::getRequestHeaders(),
            $_SERVER,
            $_COOKIE,
            []
        );
    }

    private static function getRequestHeaders(): array
    {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $normalized = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$normalized] = $value;
            }
        }

        foreach (['CONTENT_TYPE' => 'Content-Type', 'CONTENT_LENGTH' => 'Content-Length'] as $serverKey => $headerName) {
            if (isset($_SERVER[$serverKey])) {
                $headers[$headerName] = $_SERVER[$serverKey];
            }
        }
        return $headers;
    }

    private static function validateUriWithParams(string $uri): array
    {
        $matched = array_filter(
            self::$routes,
            static function ($value, $routeKey) use ($uri): bool {
                $escaped = preg_quote(ltrim($routeKey, '/'), '/');
                $regex = preg_replace('/\\\{[^\/]+\\\}/', '([^\/]+)', $escaped);
                return $regex !== null && preg_match("/^{$regex}$/", ltrim($uri, '/')) === 1;
            },
            ARRAY_FILTER_USE_BOTH
        );

        if (! empty($matched)) {
            $matchedRouteKey = array_key_first($matched);
            if ($matchedRouteKey !== null) {
                self::setParams($uri, (string) $matchedRouteKey);
            }
        }

        return array_values($matched);
    }

    private static function setParams(string $uri, string $route): void
    {
        $uriParts = explode('/', trim($uri, '/'));
        $routeParts = explode('/', trim($route, '/'));
        self::$params = array_values(array_diff($uriParts, $routeParts));
    }

    private static function loadMethod(object $controller, string $action, ServerRequestInterface $request): bool
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        switch ($method) {
            case 'POST':
                $controller->$action($request);
                break;

            case 'PUT':
            case 'PATCH':
                $controller->$action(self::$params, $request);
                break;

            case 'DELETE':
                $controller->$action(self::$params);
                break;

            default:
                if (empty(self::$params)) {
                    $controller->$action();
                } else {
                    $controller->$action(self::$params);
                }
                break;
        }

        return true;
    }

    private static function getController(array $routes): string
    {
        $classes = explode('@', $routes['controller']);
        $namespace = "App\\Controllers\\{$classes[0]}";

        if (! class_exists($namespace)) {
            throw new ClassDontExistException("Class does not exist {$classes[0]}");
        }

        return $namespace;
    }

    private static function getMethod(object $classes, array $routes): string
    {
        $method = explode('@', $routes['controller']);

        if (! method_exists($classes, $method[1])) {
            throw new MethodDontExistException("Method does not exist {$method[1]}");
        }

        return $method[1];
    }

    public static function get(string $route, string $controller, array $middlewares = []): void
    {
        self::$routes[$route] = ['controller' => $controller, 'middlewares' => $middlewares];
    }

    public static function post(string $route, string $controller, array $middlewares = []): void
    {
        self::$routes[$route] = ['controller' => $controller, 'middlewares' => $middlewares];
    }

    public static function delete(string $route, string $controller, array $middlewares = []): void
    {
        self::$routes[$route] = ['controller' => $controller, 'middlewares' => $middlewares];
    }

    public static function put(string $route, string $controller, array $middlewares = []): void
    {
        self::$routes[$route] = ['controller' => $controller, 'middlewares' => $middlewares];
    }
}
