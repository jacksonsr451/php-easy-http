<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server\Exceptions;

use Exception;
use Throwable;

class TypeError extends Exception
{
    public function __construct(string $message = 'Type error message', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
