<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Tests\Server;

use PhpEasyHttp\Http\Server\Exceptions\RouteDontExistException;
use PhpEasyHttp\Http\Server\RouteDefinition;
use PHPUnit\Framework\TestCase;

final class RouteDefinitionTest extends TestCase
{
    public function testMatchReturnsParametersWhenPathMatches(): void
    {
        $route = new RouteDefinition('GET', '/users/{id}/posts/{slug}', static fn () => null);

        $params = $route->match('GET', '/users/42/posts/hello-world');

        self::assertSame(['id' => '42', 'slug' => 'hello-world'], $params);
    }

    public function testMatchReturnsNullWhenMethodDoesNotMatch(): void
    {
        $route = new RouteDefinition('POST', '/users/{id}', static fn () => null);

        self::assertNull($route->match('GET', '/users/1'));
    }

    public function testConstructorRejectsPathWithoutLeadingSlash(): void
    {
        $this->expectException(RouteDontExistException::class);

        new RouteDefinition('GET', 'users', static fn () => null);
    }
}
