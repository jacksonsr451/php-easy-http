<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server\Exceptions;

use Exception;
use Throwable;

class NoCsrfException extends Exception
{
    public function __construct(string $message = 'CSRF token missing.', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
