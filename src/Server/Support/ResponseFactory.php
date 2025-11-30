<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Server\Support;

use JsonException;
use PhpEasyHttp\Http\Message\Interfaces\ResponseInterface;
use PhpEasyHttp\Http\Message\Response;

final class ResponseFactory
{
    public function json(mixed $data, int $status = 200, array $headers = []): ResponseInterface
    {
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $json = json_encode(['error' => 'Unable to encode response payload.']);
        }

        if ($json === false) {
            $json = '{}';
        }

        $headers = array_merge(['Content-Type' => 'application/json'], $headers);
        return $this->respond($json, $status, $headers);
    }

    public function text(string $content, int $status = 200, array $headers = []): ResponseInterface
    {
        $headers = array_merge(['Content-Type' => 'text/plain; charset=utf-8'], $headers);
        return $this->respond($content, $status, $headers);
    }

    public function html(string $content, int $status = 200, array $headers = []): ResponseInterface
    {
        $headers = array_merge(['Content-Type' => 'text/html; charset=utf-8'], $headers);
        return $this->respond($content, $status, $headers);
    }

    private function respond(string $body, int $status, array $headers): ResponseInterface
    {
        return new Response($status, $body, $headers);
    }
}
