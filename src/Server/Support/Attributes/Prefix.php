<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server\Support\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Prefix extends RoutePrefix
{
}
