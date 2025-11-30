# PHP Easy HTTP

Modern PSR-7/PSR-15 inspired toolkit for building lightweight HTTP services and FastAPI-like microservices in PHP. Ships with strict-typed message objects, a declarative router, middleware pipeline, and an ergonomic `Application` class for rapid development.

---

## Requirements

- PHP 8.2+ (tested through PHP 8.3)
- Composer for dependency management

## Installation

```bash
composer require jacksonsr451/php-easy-http
```

The package exposes all classes under the `PhpEasyHttp\Http` namespace through PSR-4 autoloading.

## Key Features

- **PSR-7 message implementations** (`Message`, `Request`, `Response`, `ServerRequest`, `Stream`, `Uri`, `UploadFiles`).
- **FastAPI-inspired `Application`** with declarative routing helpers (`get`, `post`, `put`, `patch`, `delete`).
- **Dependency Injection Container** for constructor-free service registration and parameter auto-wiring.
- **Middleware pipeline** compatible with PSR-15 style `process()` methods plus helper registration (`use`, `registerMiddleware`).
- **Automatic response normalization**: arrays/objects become JSON, scalars become JSON payloads, strings become text responses.
- **Route parameter binding & validation** with typed handler signatures.

## Project Structure

```
src/
├── Message/        # PSR-7 message implementations
├── Server/         # Application, Router, middleware, request handling
└── Server/Support/ # Container, ResponseFactory, callable handlers
```

## Quick Start

```php
<?php

use PhpEasyHttp\Http\Server\Application;

require __DIR__ . '/vendor/autoload.php';

$app = new Application();

$app->get('/ping', fn () => ['message' => 'pong']);

$app->post('/items/{id}', function (int $id, array $body) {
	return [
		'id' => $id,
		'payload' => $body,
	];
});

$app->run();
```

Visit `http://localhost:8000/ping` to receive a JSON payload.

## Declarative Routing

```php
$app->put('/users/{userId}', function (int $userId, array $body) {
	return ['userId' => $userId, 'changes' => $body];
}, options: [
	'name' => 'users.update',
	'middleware' => ['auth'],
	'summary' => 'Update a user profile',
	'tags' => ['users'],
]);
```

- Paths can contain `{param}` placeholders; values are injected by name.
- Route metadata (`name`, `summary`, `tags`) is preserved for tooling or documentation generators.

## Handler Parameter Binding

Handlers can type-hint any of the following and the application resolves them automatically:

| Parameter type | Injection source |
| --- | --- |
| `ServerRequestInterface` | Full PSR-7 request instance |
| Scalar/int/float/bool | Route parameter with implicit casting |
| `array $body` | Parsed JSON or form body |
| `array $query` | Query parameters |
| Any class name | Service container entry or auto-instantiated class |
| `ResponseFactory` | Convenience factory for JSON/text helpers |

If a parameter cannot be resolved and lacks a default value, an exception is thrown to highlight configuration issues early.

## Dependency Injection

```php
use Psr\Log\LoggerInterface;

$app->register(LoggerInterface::class, fn () => new Monolog\Logger('api'));

$app->get('/secure', function (LoggerInterface $logger) {
	$logger->info('secure endpoint accessed');
	return ['ok' => true];
});
```

- `register(string $id, callable|object|string $concrete)` binds services.
- Singleton-style instantiation: the container caches resolved instances.
- String bindings are treated as class names and instantiated lazily.

## Middleware

Implement `MiddlewareInterface` (PSR-15 style) or reuse existing classes.

```php
use PhpEasyHttp\Http\Server\Middleware;
use PhpEasyHttp\Http\Server\Interfaces\RequestHandlerInterface;
use PhpEasyHttp\Http\Message\Interfaces\ServerRequestInterface;

final class AuthMiddleware extends Middleware
{
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		if (!$request->getAttribute('user')) {
			return (new ResponseFactory())->json(['error' => 'Unauthorized'], 401);
		}

		return $handler->handle($request);
	}
}

$app->registerMiddleware('auth', AuthMiddleware::class);
$app->use('auth');                // global middleware
$app->get('/private', fn () => ['secret' => true]);
$app->get('/public', fn () => ['hello' => 'world'], options: ['middleware' => []]);
```

- `use()` attaches middleware globally in the order registered.
- Route-level middleware can be provided via the `middleware` option array.
- When registering middleware by string name, `Application` looks it up in the middleware map before instantiating.

## Response Handling

Your handler may return:

- A `ResponseInterface` if you need full control.
- An array/object → automatically encoded as JSON with `application/json` headers.
- A string → emitted as `text/plain; charset=utf-8`.
- Scalars/bools/null → wrapped in a JSON envelope (`{"data": ...}`).

You can create responses manually with `ResponseFactory`:

```php
use PhpEasyHttp\Http\Server\Support\ResponseFactory;

$app->get('/download', function (ResponseFactory $responses) {
	return $responses->text('custom body', 202, ['X-Trace' => 'abc']);
});
```

## Working with Requests

- `ServerRequest::getParsedBody()` inspects `content-type` and will decode JSON or form data automatically.
- `ServerRequest::withUploadedFiles()` accepts PSR-7 `UploadFileInterface` objects.
- Helper methods such as `inPost()` or `withAttribute()` allow you to tag requests while processing middleware.

## Running & Emitting Responses

`$app->run()` returns the generated response by default and also emits it (headers + body). To take control of the emission (useful in testing pipelines) set `emit: false`:

```php
$response = $app->run(emit: false);

// Assert, inspect, or emit manually
$app->emit($response);
```

## Testing

Create `ServerRequest` instances manually and pass them to `run()`:

```php
use PhpEasyHttp\Http\Message\ServerRequest;
use PhpEasyHttp\Http\Message\Uri;

$request = new ServerRequest('GET', new Uri('http://localhost/ping'));
$response = $app->run($request, emit: false);

$this->assertSame(200, $response->getStatusCode());
$this->assertSame('{"message":"pong"}', (string) $response->getBody());
```

## Roadmap

- Validation & schema-based request parsing
- Automatic OpenAPI generation from route metadata
- Async/worker adapters for popular PHP runtime servers

## Contributing

1. Fork the repository and create a topic branch.
2. Run `composer install` followed by `composer test` (when available).
3. Submit a pull request with a concise description of your changes and any relevant tests.

Please open an issue if you encounter bugs or have feature requests.