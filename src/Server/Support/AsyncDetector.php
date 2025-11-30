<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server\Support;

use Closure;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;

final class AsyncDetector
{
    public static function isAsync(callable $handler): bool
    {
        $reflection = self::reflect($handler);
        $file = $reflection->getFileName();
        if ($file === false) {
            return false;
        }

        $lines = @file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return false;
        }

        $line = $lines[$reflection->getStartLine() - 1] ?? '';
        $trimmed = ltrim($line);

        return str_starts_with($trimmed, 'async function')
            || str_starts_with($trimmed, 'async static function')
            || str_starts_with($trimmed, 'async public function')
            || str_starts_with($trimmed, 'async private function');
    }

    private static function reflect(callable $handler): ReflectionFunctionAbstract
    {
        if ($handler instanceof Closure) {
            return new ReflectionFunction($handler);
        }

        if (is_array($handler)) {
            return new ReflectionMethod($handler[0], $handler[1]);
        }

        if (is_string($handler) && str_contains($handler, '::')) {
            [$class, $method] = explode('::', $handler, 2);
            return new ReflectionMethod($class, $method);
        }

        if (is_object($handler) && method_exists($handler, '__invoke')) {
            return new ReflectionMethod($handler, '__invoke');
        }

        return new ReflectionFunction($handler);
    }
}
