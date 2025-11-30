<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server\Support\Attributes;

use Attribute;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_CLASS)]
class RoutePrefix
{
    public function __construct(private readonly string $path)
    {
        if ($path === '') {
            throw new InvalidArgumentException('RoutePrefix attribute requires a non-empty path.');
        }
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
