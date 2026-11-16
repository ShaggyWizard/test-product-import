<?php

declare(strict_types=1);

namespace App\Http;

use App\Service\Auth\AuthService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Пропускает запрос только с действующим токеном: Authorization: Bearer <JWT>. Иначе 401.
 */
final readonly class JwtAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthService $auth,
        private ResponseFactoryInterface $responses,
        private JsonResponder $json,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');
        $user = preg_match('/^Bearer\s+(\S+)$/i', $header, $m) === 1 ? $this->auth->verify($m[1]) : null;

        if ($user === null) {
            return $this->json
                ->error($this->responses->createResponse(), $header === '' ? 'Нужен вход.' : 'Сессия истекла, войдите снова.', 401)
                ->withHeader('WWW-Authenticate', 'Bearer');
        }

        return $handler->handle($request->withAttribute('user', $user));
    }
}
