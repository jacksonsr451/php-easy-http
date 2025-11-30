<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server;

use Closure;
use PhpEasyHttp\Http\Message\Interfaces\ResponseInterface;
use PhpEasyHttp\Http\Message\Interfaces\ServerRequestInterface;
use PhpEasyHttp\Http\Message\Response;
use PhpEasyHttp\Http\Server\Exceptions\MiddlewareException;
use PhpEasyHttp\Http\Server\Interfaces\MiddlewareInterface;
use PhpEasyHttp\Http\Server\Interfaces\RequestHandlerInterface;

class RequestHandler implements RequestHandlerInterface
{
    private array $middleware = [];

    private Closure $controller;

    private array $args = [];

    private static array $map = [];

    private static array $default = [];

    public function __construct(array $middleware, Closure $controller, array $args)
    {
        $this->middleware = array_merge(self::$default, $middleware);
        $this->controller = $controller;
        $this->args = $args;
    }

    public static function setMap(array $map = []): void
    {
        self::$map = $map;
    }

    public static function setDefault(array $default = []): void
    {
        self::$default = $default;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (empty($this->middleware)) {
            return new Response(200, call_user_func_array($this->controller, $this->args), []);
        }

        $middlewareKey = array_shift($this->middleware);

        if (! isset(self::$map[$middlewareKey])) {
            throw new MiddlewareException("Middleware {$middlewareKey} does not exist in the map.", 500);
        }

        $middlewareEntry = self::$map[$middlewareKey];
        if (is_string($middlewareEntry)) {
            if (! is_subclass_of($middlewareEntry, MiddlewareInterface::class)) {
                throw new MiddlewareException("Middleware {$middlewareEntry} must implement MiddlewareInterface.", 500);
            }
            $middlewareInstance = new $middlewareEntry();
        } elseif ($middlewareEntry instanceof MiddlewareInterface) {
            $middlewareInstance = clone $middlewareEntry;
        } else {
            throw new MiddlewareException('Middleware map must contain class names or MiddlewareInterface instances.', 500);
        }

        $handle = clone $this;
        return $middlewareInstance->process($request, $handle);
    }
}
