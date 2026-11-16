<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Ограничение частоты запросов по IP клиента. Превышение — 429 и заголовок Retry-After.
 * IP берётся из REMOTE_ADDR: nginx передаёт в PHP-FPM адрес клиента через fastcgi_params.
 */
final readonly class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RateLimiterFactory $limiters,
        private ResponseFactoryInterface $responses,
        private JsonResponder $json,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
        $limit = $this->limiters->create($ip)->consume();

        $headers = [
            'X-RateLimit-Limit' => (string) $limit->getLimit(),
            'X-RateLimit-Remaining' => (string) $limit->getRemainingTokens(),
        ];

        if (!$limit->isAccepted()) {
            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());
            $response = $this->json->error(
                $this->responses->createResponse(),
                \sprintf('Слишком много импортов. Повторите через %d с.', $retryAfter),
                429,
            )->withHeader('Retry-After', (string) $retryAfter);
        } else {
            $response = $handler->handle($request);
        }

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
