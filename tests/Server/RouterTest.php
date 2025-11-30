<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Tests\Server;

use PhpEasyHttp\Http\Message\ServerRequest;
use PhpEasyHttp\Http\Message\Uri;
use PhpEasyHttp\Http\Server\Exceptions\RouteDontExistException;
use PhpEasyHttp\Http\Server\RouteDefinition;
use PhpEasyHttp\Http\Server\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testMatchReturnsRouteAndParameters(): void
    {
        $router = new Router();
        $statusRoute = new RouteDefinition('GET', '/status', static fn () => 'ok');
        $userRoute = new RouteDefinition('GET', '/users/{id}', static fn () => 'user');

        $router->add($statusRoute);
        $router->add($userRoute);

        $request = new ServerRequest('GET', new Uri('https://example.com/users/5'));

        [$matchedRoute, $params, $isAsync] = $router->match($request);

        self::assertSame($userRoute, $matchedRoute);
        self::assertSame(['id' => '5'], $params);
        self::assertFalse($isAsync);
    }

    public function testMatchThrowsWhenNoRouteExists(): void
    {
        $router = new Router();
        $router->add(new RouteDefinition('GET', '/ping', static fn () => 'pong'));
        $request = new ServerRequest('POST', new Uri('https://example.com/ping'));

        $this->expectException(RouteDontExistException::class);

        $router->match($request);
    }
}
