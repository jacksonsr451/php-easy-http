<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server\Exceptions;

use Exception;
use Throwable;

class InvalidCsrfException extends Exception
{
    public function __construct(string $message = 'Invalid CSRF token.', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
