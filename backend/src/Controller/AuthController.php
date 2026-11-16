<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponder;
use App\Http\ValidationException;
use App\Service\Auth\AuthService;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class AuthController
{
    public function __construct(
        private AuthService $auth,
        private JsonResponder $json,
    ) {}

    #[OA\Post(
        path: '/api/auth/login',
        summary: 'Вход: логин и пароль администратора из .env → JWT',
        security: [],
        tags: ['Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['login', 'password'],
            properties: [
                new OA\Property(property: 'login', type: 'string', example: 'admin'),
                new OA\Property(property: 'password', type: 'string', example: 'admin'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Токен', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'token', type: 'string'),
                new OA\Property(property: 'expiresAt', description: 'Unix time', type: 'integer'),
            ])),
            new OA\Response(response: 401, description: 'Неверный логин или пароль', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 422, description: 'Не переданы поля', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        $login = \is_array($body) && \is_string($body['login'] ?? null) ? trim($body['login']) : '';
        $password = \is_array($body) && \is_string($body['password'] ?? null) ? $body['password'] : '';

        $errors = array_filter([
            'login' => $login === '' ? 'Введите логин.' : null,
            'password' => $password === '' ? 'Введите пароль.' : null,
        ]);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $session = $this->auth->login($login, $password);
        if ($session === null) {
            return $this->json->error($response, 'Неверный логин или пароль.', 401);
        }

        return $this->json->send($response, $session);
    }
}
