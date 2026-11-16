<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Http\Message\ResponseInterface;

final class JsonResponder
{
    public function send(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(
            json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR),
        );

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }

    /**
     * @param array<string, mixed> $details
     */
    public function error(ResponseInterface $response, string $message, int $status, array $details = []): ResponseInterface
    {
        return $this->send($response, ['error' => $message] + ($details === [] ? [] : ['details' => $details]), $status);
    }
}
