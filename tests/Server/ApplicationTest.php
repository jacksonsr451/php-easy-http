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
use PhpEasyHttp\Http\Server\Support\ResponseFactory;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    public function testRunExecutesMiddlewareAndResolvesDependencies(): void
    {
        $application = new Application();
        $log = [];
        $application->use(new RecordingMiddleware('global', $log));
        $routeMiddleware = new RecordingMiddleware('route', $log);

        $application->get('/users/{id}', function (int $id, ResponseFactory $responses) {
            return $responses->json(['id' => $id]);
        }, ['middleware' => [$routeMiddleware]]);

        $response = $application->run(
            new ServerRequest('GET', new Uri('https://example.com/users/99')),
            emit: false
        );

        self::assertSame(['global', 'route'], $log);
        self::assertSame('application/json', $response->getHeaderLine('content-type'));
        self::assertSame('{"id":99}', (string) $response->getBody());
    }

    public function testBodyParameterProvidesDecodedPayload(): void
    {
        $application = new Application();
        $application->post('/payload', static fn (array $body) => $body);

        $request = new ServerRequest(
            'POST',
            new Uri('https://example.com/payload'),
            ['Content-Type' => 'application/json'],
            body: '{"name":"Ada"}'
        );

        $response = $application->run($request, emit: false);

        self::assertSame('{"name":"Ada"}', (string) $response->getBody());
    }
}

final class RecordingMiddleware implements MiddlewareInterface
{
    /** @var array<int, string> */
    private array $log;

    public function __construct(private readonly string $name, array &$log)
    {
        $this->log =& $log;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->log[] = $this->name;
        return $handler->handle($request);
    }
}
