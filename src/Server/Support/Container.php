<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server\Support;

use InvalidArgumentException;

final class Container
{
    /** @var array<string, callable|object|string> */
    private array $bindings = [];

    /** @var array<string, object> */
    private array $instances = [];

    public function set(string $id, callable|object|string $concrete): void
    {
        $this->bindings[$id] = $concrete;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->bindings) || array_key_exists($id, $this->instances);
    }

    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (! isset($this->bindings[$id])) {
            throw new InvalidArgumentException("No entry found for identifier {$id}");
        }

        $resolved = $this->resolve($this->bindings[$id]);
        $this->instances[$id] = $resolved;

        return $resolved;
    }

    private function resolve(callable|object|string $concrete): object
    {
        if (is_object($concrete) && ! $concrete instanceof \Closure) {
            return $concrete;
        }

        if (is_string($concrete)) {
            if (! class_exists($concrete)) {
                throw new InvalidArgumentException("Class {$concrete} does not exist");
            }

            return new $concrete();
        }

        $result = $concrete($this);
        if (! is_object($result)) {
            throw new InvalidArgumentException('Container factory must return an object instance.');
        }

        return $result;
    }
}
