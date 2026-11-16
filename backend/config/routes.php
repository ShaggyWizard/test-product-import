<?php

declare(strict_types=1);

use App\Controller\HealthController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return static function (App $app): void {
    // Не static: Slim привязывает замыкание группы к контейнеру.
    $app->group('/api', function (RouteCollectorProxy $api): void {
        $api->get('/health', HealthController::class);
    });
};
