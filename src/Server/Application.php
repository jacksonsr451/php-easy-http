<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server;

use Closure;
use InvalidArgumentException;
use PhpEasyHttp\Http\Message\Interfaces\ResponseInterface;
use PhpEasyHttp\Http\Message\Interfaces\ServerRequestInterface;
use PhpEasyHttp\Http\Message\ServerRequest;
use PhpEasyHttp\Http\Message\Stream;
use PhpEasyHttp\Http\Message\Uri;
use PhpEasyHttp\Http\Server\Exceptions\MiddlewareException;
use PhpEasyHttp\Http\Server\Interfaces\MiddlewareInterface;
use PhpEasyHttp\Http\Server\Interfaces\RequestHandlerInterface;
use PhpEasyHttp\Http\Server\Support\CallableRequestHandler;
use PhpEasyHttp\Http\Server\Support\Container;
use PhpEasyHttp\Http\Server\Support\ResponseFactory;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

class Application
{
    private Router $router;

    private Container $container;

    /** @var array<string, MiddlewareInterface|callable|string> */
    private array $middlewareMap = [];

    /** @var array<int, MiddlewareInterface|callable|string> */
    private array $globalMiddleware = [];

    public function __construct(?Router $router = null, ?Container $container = null)
    {
        $this->router = $router ?? new Router();
        $this->container = $container ?? new Container();
        $this->container->set(ResponseFactory::class, fn () => new ResponseFactory());
    }

    public function register(string $id, callable|object|string $concrete): void
    {
        $this->container->set($id, $concrete);
    }

    public function registerMiddleware(string $name, MiddlewareInterface|callable|string $middleware): void
    {
        $this->middlewareMap[$name] = $middleware;
    }

    public function use(string|MiddlewareInterface|callable $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    public function get(string $path, callable $handler, array $options = []): void
    {
        $this->addRoute('GET', $path, $handler, $options);
    }

    public function post(string $path, callable $handler, array $options = []): void
    {
        $this->addRoute('POST', $path, $handler, $options);
    }

    public function put(string $path, callable $handler, array $options = []): void
    {
        $this->addRoute('PUT', $path, $handler, $options);
    }

    public function patch(string $path, callable $handler, array $options = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $options);
    }

    public function delete(string $path, callable $handler, array $options = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $options);
    }

    public function addRoute(string $method, string $path, callable $handler, array $options = []): void
    {
        $middleware = $options['middleware'] ?? [];
        if (! is_array($middleware)) {
            throw new InvalidArgumentException('Route middleware definition must be an array.');
        }

        $route = new RouteDefinition(
            strtoupper($method),
            $path,
            $handler,
            $middleware,
            $options['name'] ?? null,
            $options['summary'] ?? null,
            $options['tags'] ?? []
        );

        $this->router->add($route);
    }

    public function run(?ServerRequestInterface $request = null, bool $emit = true): ResponseInterface
    {
        $request ??= $this->createRequestFromGlobals();

        [$route, $params] = $this->router->match($request);
        foreach ($params as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        $core = function (ServerRequestInterface $serverRequest) use ($route, $params): ResponseInterface {
            $result = $this->invokeHandler($route->getHandler(), $serverRequest, $params);
            return $this->normalizeResponse($result);
        };

        $middlewares = array_merge($this->globalMiddleware, $route->getMiddleware());
        $resolvedMiddleware = array_map(fn ($middleware) => $this->resolveMiddleware($middleware), $middlewares);

        $response = $this->runPipeline($resolvedMiddleware, $core, $request);

        if ($emit) {
            $this->emit($response);
        }

        return $response;
    }

    public function emit(ResponseInterface $response): void
    {
        http_response_code($response->getStatusCode());
        foreach ($response->getHeaders() as $name => $values) {
            header($this->normalizeHeaderName($name) . ': ' . implode(',', $values));
        }

        echo (string) $response->getBody();
    }

    private function runPipeline(array $middleware, callable $core, ServerRequestInterface $request): ResponseInterface
    {
        $handler = new CallableRequestHandler(fn (ServerRequestInterface $req): ResponseInterface => $core($req));

        foreach (array_reverse($middleware) as $layer) {
            $current = $layer;
            $handler = new class($current, $handler) implements RequestHandlerInterface {
                public function __construct(
                    private readonly MiddlewareInterface $middleware,
                    private readonly RequestHandlerInterface $next
                ) {
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->middleware->process($request, $this->next);
                }
            };
        }

        return $handler->handle($request);
    }

    private function resolveMiddleware(MiddlewareInterface|callable|string $middleware): MiddlewareInterface
    {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        if (is_string($middleware)) {
            $target = $this->middlewareMap[$middleware] ?? $middleware;
            if (is_string($target)) {
                if (! class_exists($target)) {
                    throw new MiddlewareException("Middleware {$target} does not exist.");
                }

                $instance = new $target();
                if (! $instance instanceof MiddlewareInterface) {
                    throw new MiddlewareException("Middleware {$target} must implement MiddlewareInterface.");
                }

                return $instance;
            }

            $middleware = $target;
        }

        if (is_callable($middleware)) {
            $resolved = $middleware($this->container);
            if (! $resolved instanceof MiddlewareInterface) {
                throw new MiddlewareException('Resolved middleware must implement MiddlewareInterface.');
            }

            return $resolved;
        }

        throw new MiddlewareException('Unable to resolve middleware definition.');
    }

    private function invokeHandler(callable $handler, ServerRequestInterface $request, array $params): mixed
    {
        $reflection = $this->createReflection($handler);
        $arguments = [];

        foreach ($reflection->getParameters() as $parameter) {
            $arguments[] = $this->resolveParameter($parameter, $request, $params);
        }

        return $handler(...$arguments);
    }

    private function resolveParameter(ReflectionParameter $parameter, ServerRequestInterface $request, array $params): mixed
    {
        $type = $parameter->getType();
        $name = $parameter->getName();

        if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
            $className = $type->getName();

            if (is_a($className, ServerRequestInterface::class, true)) {
                return $request;
            }

            if ($className === ResponseFactory::class) {
                return $this->container->get(ResponseFactory::class);
            }

            if ($this->container->has($className)) {
                return $this->container->get($className);
            }

            if (isset($params[$name])) {
                return $this->castValue($params[$name], $type);
            }

            throw new RuntimeException("Unable to resolve parameter {$name}");
        }

        if (isset($params[$name])) {
            return $this->castValue($params[$name], $type);
        }

        if ($name === 'body') {
            return $this->extractBody($request);
        }

        if ($name === 'query') {
            return $request->getQueryParams();
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($type instanceof ReflectionNamedType && $type->getName() === 'array') {
            return $params;
        }

        throw new RuntimeException("Unable to resolve value for parameter {$name}");
    }

    private function castValue(mixed $value, ?ReflectionNamedType $type): mixed
    {
        if ($type === null) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
            'string' => (string) $value,
            'array' => (array) $value,
            default => $value,
        };
    }

    private function extractBody(ServerRequestInterface $request): mixed
    {
        $body = (string) $request->getBody();
        if ($body === '') {
            return [];
        }

        $contentType = strtolower($request->getHeaderLine('content-type'));

        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($body, true);
            return $decoded ?? [];
        }

        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            parse_str($body, $parsed);
            return $parsed;
        }

        return $body;
    }

    private function normalizeResponse(mixed $result): ResponseInterface
    {
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        $factory = $this->container->get(ResponseFactory::class);

        if (is_array($result) || is_object($result)) {
            return $factory->json($result);
        }

        if (is_string($result)) {
            return $factory->text($result);
        }

        if (is_numeric($result) || is_bool($result) || $result === null) {
            return $factory->json(['data' => $result]);
        }

        throw new RuntimeException('Unsupported response type returned from handler.');
    }

    private function createRequestFromGlobals(): ServerRequestInterface
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = new Uri(sprintf('%s://%s%s', $scheme, $host, $requestUri));

        $headers = $this->getHeadersFromGlobals();
        $resource = fopen('php://input', 'r');
        $body = $resource === false ? null : new Stream($resource);

        return new ServerRequest($method, $uri, $headers, $_SERVER, $_COOKIE, [], $body);
    }

    private function getHeadersFromGlobals(): array
    {
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

    private function normalizeHeaderName(string $name): string
    {
        return str_replace(' ', '-', ucwords(strtolower(str_replace('-', ' ', $name))));
    }

    private function createReflection(callable $handler): ReflectionFunction|ReflectionMethod
    {
        if (is_array($handler)) {
            return new ReflectionMethod($handler[0], $handler[1]);
        }

        if ($handler instanceof Closure) {
            return new ReflectionFunction($handler);
        }

        if (is_object($handler) && method_exists($handler, '__invoke')) {
            return new ReflectionMethod($handler, '__invoke');
        }

        return new ReflectionFunction($handler);
    }
}
