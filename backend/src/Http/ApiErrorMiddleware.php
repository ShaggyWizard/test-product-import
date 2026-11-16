<?php

declare(strict_types=1);

namespace App\Http;

use App\Service\Product\ProductConflictException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpException;
use Slim\Exception\HttpMethodNotAllowedException;

/**
 * Единый формат ошибок API: {"error": "...", "details": {...}}.
 * Ожидаемые ошибки (валидация, конфликт, 404) превращаются в ответ здесь;
 * всё остальное уходит в ErrorMiddleware Slim и логируется как 500.
 */
final readonly class ApiErrorMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ResponseFactoryInterface $responses,
        private JsonResponder $json,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (ValidationException $e) {
            return $this->json->error($this->responses->createResponse(), $e->getMessage(), 422, $e->errors);
        } catch (ProductConflictException $e) {
            return $this->json->error($this->responses->createResponse(), $e->getMessage(), 409);
        } catch (HttpException $e) {
            if ($e->getCode() >= 500) {
                throw $e;
            }
            $response = $this->json->error($this->responses->createResponse(), $e->getMessage(), $e->getCode());

            return $e instanceof HttpMethodNotAllowedException
                ? $response->withHeader('Allow', implode(', ', $e->getAllowedMethods()))
                : $response;
        }
    }
}
