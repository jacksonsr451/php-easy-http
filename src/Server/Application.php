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
use PhpEasyHttp\Http\Server\Support\AsyncDispatcher;
use PhpEasyHttp\Http\Server\Support\CallableRequestHandler;
use PhpEasyHttp\Http\Server\Support\Container;
use PhpEasyHttp\Http\Server\Support\OpenApi\OpenApiGenerator;
use PhpEasyHttp\Http\Server\Support\SyncDispatcher;
use PhpEasyHttp\Http\Server\Support\ResponseFactory;
use PhpEasyHttp\Http\Server\Support\RouteDocBlockParser;
use ReflectionClass;
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

    private bool $docsEnabled = false;

    private AsyncDispatcher $asyncDispatcher;

    private SyncDispatcher $syncDispatcher;

    /** @var array{
     *     title?: string,
     *     version?: string,
     *     description?: string|null,
     *     servers?: array<int, array<string, mixed>>,
     *     openapiPath: string,
     *     swaggerPath: string,
     *     redocPath: string,
     *     redocScriptPath: string
     * } */
    private array $docsOptions = [
        'title' => 'php-easy-http API',
        'version' => '1.0.0',
        'description' => null,
        'servers' => null,
        'openapiPath' => '/openapi.json',
        'swaggerPath' => '/docs',
        'redocPath' => '/redoc',
        'redocScriptPath' => '/openapi/redoc.js',
    ];

    private ?string $redocBundleCache = null;

    public function __construct(?Router $router = null, ?Container $container = null)
    {
        $this->router = $router ?? new Router();
        $this->container = $container ?? new Container();
        $this->container->set(ResponseFactory::class, fn () => new ResponseFactory());
        $this->asyncDispatcher = new AsyncDispatcher();
        $this->syncDispatcher = new SyncDispatcher();
    }

    /**
     * Enable automatic OpenAPI generation plus Swagger UI and ReDoc viewer routes.
     */
    public function enableAutoDocs(array $options = []): void
    {
        $this->docsOptions = array_merge($this->docsOptions, $options);

        if ($this->docsEnabled) {
            return;
        }

        $this->docsEnabled = true;
        $this->registerDocumentationRoutes();
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

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Register controllers that describe their routes via PHPDoc comments.
     *
     * Each public method that contains an `@Route` directive will be converted into a
     * route definition, mirroring FastAPI's decorator style without a dedicated routes file.
     *
     * Example:
     * ```php
    * /**
    *  * @Route GET /users/{id}
    *  * @Summary Show a user
    *  * @Tags users,read
    *  * @Middleware auth
    *  *
    *  * @return array
    *  *\/
    * public function show(int $id): array { // ... }
     * ```
     *
     * @param array<int, string|object>|string|object $controllers
     */
    public function registerControllers(string|object|array $controllers): void
    {
        $targets = is_array($controllers) ? $controllers : [$controllers];
        $parser = new RouteDocBlockParser();

        foreach ($targets as $controller) {
            $className = is_string($controller) ? $controller : $controller::class;

            if (! class_exists($className)) {
                throw new InvalidArgumentException("Controller {$className} does not exist.");
            }

            $reflection = new ReflectionClass($className);
            $routes = $parser->parse($reflection);
            if ($routes === []) {
                continue;
            }

            $instance = is_object($controller) ? $controller : $this->resolveControllerInstance($className);

            foreach ($routes as $definition) {
                $this->addRoute(
                    $definition['httpMethod'],
                    $definition['path'],
                    [$instance, $definition['methodName']],
                    [
                        'middleware' => $definition['middleware'],
                        'name' => $definition['name'],
                        'summary' => $definition['summary'],
                        'description' => $definition['description'] ?? null,
                        'responses' => $definition['responses'] ?? null,
                        'tags' => $definition['tags'],
                    ]
                );
            }
        }
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

        $responses = $options['responses'] ?? null;
        if ($responses !== null && ! is_array($responses)) {
            throw new InvalidArgumentException('Route responses definition must be an array when provided.');
        }

        $route = new RouteDefinition(
            strtoupper($method),
            $path,
            $handler,
            $middleware,
            $options['name'] ?? null,
            $options['summary'] ?? null,
            $options['description'] ?? null,
            $options['tags'] ?? [],
            $responses,
        );

        $this->router->add($route);
    }

    public function run(?ServerRequestInterface $request = null, bool $emit = true): ResponseInterface
    {
        $request ??= $this->createRequestFromGlobals();

        try {
            [$route, $params, $isAsync] = $this->router->match($request);
        } catch (Exceptions\RouteDontExistException $exception) {
            $factory = $this->container->get(ResponseFactory::class);
            $response = $factory->json([
                'error' => 'Route not found',
                'method' => $request->getMethod(),
                'path' => (string) $request->getUri()->getPath(),
            ], 404);

            if ($emit) {
                $this->emit($response);
            }

            return $response;
        }
        foreach ($params as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        $core = function (ServerRequestInterface $serverRequest) use ($route, $params, $isAsync): ResponseInterface {
            $result = $this->invokeHandler($route->getHandler(), $serverRequest, $params, $isAsync);
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

        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
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

    private function resolveControllerInstance(string $controller): object
    {
        if ($this->container->has($controller)) {
            return $this->container->get($controller);
        }

        if (! class_exists($controller)) {
            throw new InvalidArgumentException("Controller {$controller} does not exist.");
        }

        return new $controller();
    }

    private function invokeHandler(callable $handler, ServerRequestInterface $request, array $params, bool $isAsync): mixed
    {
        $reflection = $this->createReflection($handler);
        $arguments = [];

        foreach ($reflection->getParameters() as $parameter) {
            $arguments[] = $this->resolveParameter($parameter, $request, $params);
        }

        $callable = static fn () => $handler(...$arguments);

        if ($isAsync) {
            return $this->asyncDispatcher->dispatch($callable);
        }

        return $this->syncDispatcher->dispatch($callable);
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

    private function registerDocumentationRoutes(): void
    {
        $this->get($this->docsOptions['openapiPath'], function (ResponseFactory $responses): ResponseInterface {
            $generator = new OpenApiGenerator($this->router, $this->docsOptions);
            return $responses->json($generator->generate());
        });

        $this->get($this->docsOptions['swaggerPath'], function (ResponseFactory $responses): ResponseInterface {
            return $responses->html($this->renderSwaggerUi($this->docsOptions['openapiPath']));
        });

        $scriptPath = $this->docsOptions['redocScriptPath'];

        $this->get($scriptPath, function (ResponseFactory $responses): ResponseInterface {
            return $responses->text(
                $this->getRedocBundle(),
                200,
                [
                    'Content-Type' => 'application/javascript; charset=utf-8',
                    'Cache-Control' => 'public, max-age=86400',
                ]
            );
        });

        $this->get($this->docsOptions['redocPath'], function (ResponseFactory $responses) use ($scriptPath): ResponseInterface {
            return $responses->html($this->renderRedoc($this->docsOptions['openapiPath'], $scriptPath));
        });
    }

    private function renderSwaggerUi(string $specPath): string
    {
        $spec = htmlspecialchars($specPath, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <title>Swagger UI</title>
        <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" />
    </head>
    <body>
        <div id="swagger-ui"></div>
        <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js" crossorigin="anonymous"></script>
        <script>
            window.SwaggerUIBundle({
                url: '{$spec}',
                dom_id: '#swagger-ui'
            });
        </script>
    </body>
</html>
HTML;
    }

    private function renderRedoc(string $specPath, string $scriptPath): string
    {
        $spec = htmlspecialchars($specPath, ENT_QUOTES, 'UTF-8');
        $script = htmlspecialchars($scriptPath, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <title>ReDoc</title>
        <style>
            html, body { margin: 0; padding: 0; height: 100%; }
            redoc { display: block; height: 100%; }
        </style>
        <script src="{$script}" defer></script>
    </head>
    <body>
        <redoc spec-url="{$spec}"></redoc>
    </body>
</html>
HTML;
    }

    private function getRedocBundle(): string
    {
        if ($this->redocBundleCache !== null) {
            return $this->redocBundleCache;
        }

        $path = __DIR__ . '/Support/OpenApi/redoc.standalone.js';
        if (! is_file($path)) {
            throw new RuntimeException('ReDoc bundle is missing from ' . $path);
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Unable to read ReDoc bundle from ' . $path);
        }

        return $this->redocBundleCache = $contents;
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
