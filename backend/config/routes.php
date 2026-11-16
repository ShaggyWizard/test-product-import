<?php

declare(strict_types=1);

use App\Controller\DocsController;
use App\Controller\HealthController;
use App\Controller\ImportController;
use App\Controller\ProductController;
use App\Http\RateLimitMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return static function (App $app): void {
    // Не static: Slim привязывает замыкание группы к контейнеру.
    $app->group('/api', function (RouteCollectorProxy $api): void {
        $api->get('/health', HealthController::class);

        $api->get('/products', [ProductController::class, 'list']);
        $api->post('/products', [ProductController::class, 'create']);
        $api->get('/products/{id}', [ProductController::class, 'show']);
        $api->put('/products/{id}', [ProductController::class, 'update']);
        $api->delete('/products/{id}', [ProductController::class, 'delete']);

        // Rate limit — только на загрузку файла; опрос статуса не ограничен.
        $api->post('/import', [ImportController::class, 'start'])->add(RateLimitMiddleware::class);
        $api->get('/import/latest', [ImportController::class, 'latest']);
        $api->get('/import/{id}', [ImportController::class, 'show']);

        $api->get('/openapi.json', [DocsController::class, 'spec']);
        $api->get('/docs', [DocsController::class, 'ui']);
    });
};
