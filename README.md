# PHP Easy HTTP - PSR 7 

## Requirements
- PHP 8.2 or newer (fully compatible with PHP 8.3)

## Highlights
- Strict typing across the message/server components for safer APIs
- FastAPI-inspired `Application` for expressive microservices
- Built-in dependency injection, middleware pipeline, and automatic JSON responses
- Middleware-ready server tooling with improved error handling

## Quick start

```php
<?php

use PhpEasyHttp\Http\Server\Application;
use PhpEasyHttp\Http\Server\Support\ResponseFactory;

require __DIR__ . '/vendor/autoload.php';

$app = new Application();

$app->get('/ping', fn () => ['message' => 'pong']);

$app->post('/items/{id}', function (int $id, array $body, ResponseFactory $responses) {
	return $responses->json([
		'id' => $id,
		'payload' => $body,
	]);
});

$app->run();
```

### Dependency injection & middleware

```php
$app->register(LoggerInterface::class, fn () => new Logger());
$app->registerMiddleware('csrf', CsrfTokenMiddleware::class);
$app->use('csrf'); // global middleware

$app->get('/secure', function (LoggerInterface $logger) {
	$logger->info('secure endpoint accessed');
	return ['ok' => true];
});
```

- Handler parameters accept `ServerRequestInterface`, route params, `array $body`, `array $query`, or any class bound in the container.
- Return a `ResponseInterface`, array/object (auto JSON), scalar, or string.
- Toggle output emission with `$app->run(emit: false)` if you want to handle the response manually.