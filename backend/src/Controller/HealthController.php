<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponder;
use Doctrine\DBAL\Connection;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class HealthController
{
    public function __construct(
        private Connection $connection,
        private JsonResponder $json,
    ) {
    }

    #[OA\Get(
        path: '/api/health',
        summary: 'Проверка, что приложение и БД живы',
        tags: ['System'],
        responses: [
            new OA\Response(response: 200, description: 'Всё работает'),
            new OA\Response(response: 503, description: 'БД недоступна'),
        ],
    )]
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $this->connection->executeQuery('SELECT 1');
            $db = 'ok';
        } catch (\Throwable) {
            $db = 'down';
        }

        $ok = $db === 'ok';

        return $this->json->send($response, [
            'status' => $ok ? 'ok' : 'degraded',
            'checks' => ['database' => $db],
            'time' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ], $ok ? 200 : 503);
    }
}
