<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Tests\Server\Support;

use InvalidArgumentException;
use PhpEasyHttp\Http\Server\Support\Container;
use PHPUnit\Framework\TestCase;

final class ContainerTest extends TestCase
{
    public function testResolvesStringBindingsAndCachesInstances(): void
    {
        $container = new Container();
        $container->set(SampleService::class, SampleService::class);

        $first = $container->get(SampleService::class);
        $second = $container->get(SampleService::class);

        self::assertInstanceOf(SampleService::class, $first);
        self::assertSame($first, $second, 'Container should cache resolved instances.');
    }

    public function testResolvesClosures(): void
    {
        $container = new Container();
        $container->set('factory', static fn (): SampleService => new SampleService('factory'));

        $resolved = $container->get('factory');

        self::assertInstanceOf(SampleService::class, $resolved);
        self::assertSame('factory', $resolved->name);
    }

    public function testThrowsWhenClassDoesNotExist(): void
    {
        $container = new Container();
        $container->set('ghost', 'Not\Existing\Class');

        $this->expectException(InvalidArgumentException::class);

        $container->get('ghost');
    }
}

final class SampleService
{
    public function __construct(public string $name = 'sample')
    {
    }
}
