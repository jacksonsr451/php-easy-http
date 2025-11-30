<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server;

use ArrayAccess;
use PhpEasyHttp\Http\Message\Interfaces\ResponseInterface;
use PhpEasyHttp\Http\Message\Interfaces\ServerRequestInterface;
use PhpEasyHttp\Http\Server\Exceptions\InvalidCsrfException;
use PhpEasyHttp\Http\Server\Exceptions\NoCsrfException;
use PhpEasyHttp\Http\Server\Exceptions\TypeError;
use PhpEasyHttp\Http\Server\Interfaces\MiddlewareInterface;
use PhpEasyHttp\Http\Server\Interfaces\RequestHandlerInterface;

class CsrfTokenMiddleware implements MiddlewareInterface
{
    private array|ArrayAccess $session;

    private int $limit;

    private string $sessionKey;

    private string $formKey;

    public function __construct(array|ArrayAccess &$session, int $limit = 50, string $sessionKey = 'csrf_tokens', string $formKey = '_csrf')
    {
        $this->validateSession($session);
        $this->session = &$session;
        $this->limit = $limit;
        $this->sessionKey = $sessionKey;
        $this->formKey = $formKey;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (in_array($request->getMethod(), ['post', 'delete', 'put'], true)) {
            $params = $request->getParsedBody() ?? [];
            if (! is_array($params) || ! array_key_exists($this->formKey, $params)) {
                throw new NoCsrfException('CSRF token is required.');
            }

            $tokens = $this->session[$this->sessionKey] ?? [];
            if (! in_array($params[$this->formKey], $tokens, true)) {
                throw new InvalidCsrfException('This CSRF token is invalid.');
            }

            $this->removeToken($params[$this->formKey]);
        }

        return $handler->handle($request);
    }

    public function generateToken(): string
    {
        $token = bin2hex(random_bytes(16));
        $tokens = $this->session[$this->sessionKey] ?? [];
        $tokens[] = $token;
        $this->session[$this->sessionKey] = $this->limitToken($tokens);
        return $token;
    }

    public function validateSession(array|ArrayAccess $session): void
    {
        if (! is_array($session) && ! $session instanceof ArrayAccess) {
            throw new TypeError('Session is not in array!');
        }
    }

    public function getSessionKey(): string
    {
        return $this->sessionKey;
    }

    public function getFormKey(): string
    {
        return $this->formKey;
    }

    public function removeToken(string $token): void
    {
        $tokens = $this->session[$this->sessionKey] ?? [];

        if (is_array($tokens)) {
            $index = array_search($token, $tokens, true);
            if ($index !== false) {
                unset($tokens[$index]);
                $this->session[$this->sessionKey] = $tokens;
            }
            return;
        }

        if ($tokens instanceof ArrayAccess) {
            unset($tokens[$token]);
            $this->session[$this->sessionKey] = $tokens;
        }
    }

    public function limitToken(array $tokens): array
    {
        while (count($tokens) > $this->limit) {
            array_shift($tokens);
        }
        return $tokens;
    }
}
