<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Tests\Server\Support;

use PhpEasyHttp\Http\Server\Support\ResponseFactory;
use PHPUnit\Framework\TestCase;

final class ResponseFactoryTest extends TestCase
{
    public function testJsonResponseSerializesPayload(): void
    {
        $factory = new ResponseFactory();

        $response = $factory->json(['message' => 'ok'], 201, ['X-App' => 'php-easy-http']);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));
        self::assertSame('php-easy-http', $response->getHeaderLine('x-app'));
        self::assertSame('{"message":"ok"}', (string) $response->getBody());
    }

    public function testJsonResponseFallsBackWhenEncodingFails(): void
    {
        $factory = new ResponseFactory();

        $response = $factory->json(NAN);

        self::assertSame('application/json', $response->getHeaderLine('content-type'));
        self::assertSame('{"error":"Unable to encode response payload."}', (string) $response->getBody());
    }

    public function testTextResponseSetsPlainTextHeader(): void
    {
        $factory = new ResponseFactory();

        $response = $factory->text('ready');

        self::assertSame('text/plain; charset=utf-8', $response->getHeaderLine('content-type'));
        self::assertSame('ready', (string) $response->getBody());
    }
}
