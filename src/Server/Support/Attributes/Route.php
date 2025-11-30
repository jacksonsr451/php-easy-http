<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server\Support\Attributes;

use Attribute;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Route
{
    /** @var array<int, string> */
    private array $methods;

    /** @var array<int, string> */
    private array $middleware;

    /** @var array<int, string> */
    private array $tags;

    public function __construct(
        string|array $method,
        private readonly string $path,
        array $middleware = [],
        private readonly ?string $name = null,
        private readonly ?string $summary = null,
        array $tags = []
    ) {
        $this->methods = $this->normalizeMethods($method);
        if ($this->methods === []) {
            throw new InvalidArgumentException('Route attribute must declare at least one HTTP method.');
        }

        if ($path === '') {
            throw new InvalidArgumentException('Route attribute requires a non-empty path.');
        }

        $this->middleware = $this->normalizeList($middleware);
        $this->tags = $this->normalizeList($tags);
    }

    /**
     * @return array<int, string>
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return array<int, string>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    /**
     * @return array<int, string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @param string|array<int, string> $method
     * @return array<int, string>
     */
    private function normalizeMethods(string|array $method): array
    {
        $methods = is_array($method) ? $method : ($this->splitStringList($method) ?: [$method]);

        $normalized = [];
        foreach ($methods as $entry) {
            $value = strtoupper(trim($entry));
            if ($value !== '') {
                $normalized[$value] = $value;
            }
        }

        return array_values($normalized);
    }

    /**
     * @param array<int, string> $items
     * @return array<int, string>
     */
    private function normalizeList(array $items): array
    {
        $normalized = [];
        foreach ($items as $item) {
            $value = trim((string) $item);
            if ($value !== '') {
                $normalized[$value] = $value;
            }
        }

        return array_values($normalized);
    }

    /**
     * @return array<int, string>
     */
    private function splitStringList(string $value): array
    {
        $parts = preg_split('/[|,]/', $value);
        if ($parts === false) {
            return [];
        }

        return $parts;
    }
}
