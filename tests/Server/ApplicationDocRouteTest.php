<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Tests\Server;

use PhpEasyHttp\Http\Message\Interfaces\ResponseInterface;
use PhpEasyHttp\Http\Message\Interfaces\ServerRequestInterface;
use PhpEasyHttp\Http\Message\ServerRequest;
use PhpEasyHttp\Http\Message\Uri;
use PhpEasyHttp\Http\Server\Application;
use PhpEasyHttp\Http\Server\Interfaces\MiddlewareInterface;
use PhpEasyHttp\Http\Server\Interfaces\RequestHandlerInterface;
use PhpEasyHttp\Http\Server\RouteDefinition;
use PhpEasyHttp\Http\Server\Router;
use PhpEasyHttp\Http\Server\Support\Attributes\Route as RouteAttribute;
use PhpEasyHttp\Http\Server\Support\Attributes\RoutePrefix as RoutePrefixAttribute;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class ApplicationDocRouteTest extends TestCase
{
    public function testRegistersRoutesDeclaredInDocBlocks(): void
    {
        $app = new Application();
        $app->registerControllers(FakeDocCommentController::class);

        $response = $app->run(new ServerRequest('GET', new Uri('http://localhost/api/users/42')), emit: false);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"id":42}', (string) $response->getBody());

        $routes = $this->loadRoutes($app);
        $route = $this->findRoute($routes, 'GET', '/api/users/{id}');

        self::assertSame('user.show', $route->getName());
        self::assertSame('View user details', $route->getSummary());
        self::assertSame(['users', 'read'], $route->getTags());
    }

    public function testSupportsMultipleHttpMethodsFromSingleDirective(): void
    {
        $app = new Application();
        $app->registerControllers(FakeDocCommentController::class);

        $postResponse = $app->run(new ServerRequest('POST', new Uri('http://localhost/api/status')), emit: false);
        self::assertSame(200, $postResponse->getStatusCode());
        self::assertSame('{"status":"ok"}', (string) $postResponse->getBody());

        $routes = $this->loadRoutes($app);
        self::assertNotNull($this->findRoute($routes, 'GET', '/api/status'));
        self::assertNotNull($this->findRoute($routes, 'POST', '/api/status'));
    }

    public function testRegistersRoutesDeclaredWithAttributes(): void
    {
        $app = new Application();
        $app->registerMiddleware('auth', new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        });
        $app->registerControllers(FakeAttributeController::class);

        $getResponse = $app->run(new ServerRequest('GET', new Uri('http://localhost/v2/health')), emit: false);
        self::assertSame(200, $getResponse->getStatusCode());
        self::assertSame('{"status":"ok"}', (string) $getResponse->getBody());

        $patchResponse = $app->run(new ServerRequest('PATCH', new Uri('http://localhost/v2/users/9')), emit: false);
        self::assertSame(200, $patchResponse->getStatusCode());
        self::assertSame('{"updated":9}', (string) $patchResponse->getBody());

        $routes = $this->loadRoutes($app);

        $healthRoute = $this->findRoute($routes, 'GET', '/v2/health');
        self::assertSame('API health', $healthRoute->getSummary());
        self::assertSame(['auth'], $healthRoute->getMiddleware());
        self::assertSame(['health'], $healthRoute->getTags());

        $postHealth = $this->findRoute($routes, 'POST', '/v2/health');
        self::assertSame($healthRoute->getPath(), $postHealth->getPath());

        $updateRoute = $this->findRoute($routes, 'PUT', '/v2/users/{id}');
        self::assertSame('user.update', $updateRoute->getName());
        self::assertSame(['users'], $updateRoute->getTags());
    }

    /**
     * @return array<int, RouteDefinition>
     */
    private function loadRoutes(Application $application): array
    {
        $routerProperty = new ReflectionProperty(Application::class, 'router');
        $routerProperty->setAccessible(true);
        /** @var Router $router */
        $router = $routerProperty->getValue($application);

        $routesProperty = new ReflectionProperty(Router::class, 'routes');
        $routesProperty->setAccessible(true);
        /** @var array<int, RouteDefinition> $routes */
        $routes = $routesProperty->getValue($router);

        return $routes;
    }

    /**
     * @param array<int, RouteDefinition> $routes
     */
    private function findRoute(array $routes, string $method, string $path): RouteDefinition
    {
        foreach ($routes as $route) {
            if ($route->getMethod() === $method && $route->getPath() === $path) {
                return $route;
            }
        }

        self::fail(sprintf('Route %s %s not found', $method, $path));
        throw new \RuntimeException('Route not found');
    }
}

/**
 * @RoutePrefix /api
 */
final class FakeDocCommentController
{
    /**
     * @Route GET /users/{id}
     * @Summary View user details
     * @Tags users,read
     * @Name user.show
     */
    public function show(int $id): array
    {
        return ['id' => $id];
    }

    /**
     * @Route GET|POST /status
     */
    public function status(): array
    {
        return ['status' => 'ok'];
    }
}

#[RoutePrefixAttribute('/v2')]
final class FakeAttributeController
{
    #[RouteAttribute(method: ['GET', 'POST'], path: '/health', middleware: ['auth'], summary: 'API health', tags: ['health'])]
    public function health(): array
    {
        return ['status' => 'ok'];
    }

    #[RouteAttribute(method: ['PUT', 'PATCH'], path: '/users/{id}', name: 'user.update', tags: ['users'])]
    public function update(int $id): array
    {
        return ['updated' => $id];
    }
}
