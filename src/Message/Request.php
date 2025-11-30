<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Message;

use InvalidArgumentException;
use PhpEasyHttp\Http\Message\Interfaces\RequestInterface;
use PhpEasyHttp\Http\Message\Interfaces\UriInterface;
use PhpEasyHttp\Http\Message\Traits\MessageTrait;
use PhpEasyHttp\Http\Message\Traits\RequestTrait;

class Request implements RequestInterface
{
    use MessageTrait;
    use RequestTrait;

    public function __construct(string $method, UriInterface|string $uri, array $headers = [], mixed $body = null, string $version = '1.1')
    {
        $this->method = strtolower($method);
        if (! in_array($this->method, $this->validMethods, true)) {
            throw new InvalidArgumentException('Unsupported HTTP method provided.');
        }
        $this->protocol = $version;
        $this->setUri($uri);
        $this->setHeaders($headers);
        $this->setBody($body);
    }
}
