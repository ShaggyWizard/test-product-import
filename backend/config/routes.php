<?php

declare(strict_types=1);

use App\Controller\AuthController;
use App\Controller\DocsController;
use App\Controller\HealthController;
use App\Controller\ImportController;
use App\Controller\ProductController;
use App\Http\JwtAuthMiddleware;
use App\Http\RateLimitMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return static function (App $app): void {
    // Не static: Slim привязывает замыкание группы к контейнеру.
    $app->group('/api', function (RouteCollectorProxy $api): void {
        // Открыто без входа.
        $api->get('/health', HealthController::class);
        $api->post('/auth/login', [AuthController::class, 'login']);
        $api->get('/openapi.json', [DocsController::class, 'spec']);
        $api->get('/docs', [DocsController::class, 'ui']);

        // Только с токеном (Authorization: Bearer <JWT>).
        $api->group('', function (RouteCollectorProxy $private): void {
            $private->get('/products', [ProductController::class, 'list']);
            $private->post('/products', [ProductController::class, 'create']);
            $private->get('/products/{id}', [ProductController::class, 'show']);
            $private->put('/products/{id}', [ProductController::class, 'update']);
            $private->delete('/products/{id}', [ProductController::class, 'delete']);

            // Rate limit — только на загрузку файла; опрос статуса не ограничен.
            $private->post('/import', [ImportController::class, 'start'])->add(RateLimitMiddleware::class);
            $private->get('/import/latest', [ImportController::class, 'latest']);
            $private->get('/import/{id}', [ImportController::class, 'show']);
        })->add(JwtAuthMiddleware::class);
    });
};
