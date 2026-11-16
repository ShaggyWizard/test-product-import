<?php

declare(strict_types=1);

use App\Config\Settings;
use App\Http\ApiErrorMiddleware;
use Psr\Container\ContainerInterface;
use Slim\Factory\AppFactory;
use Slim\Handlers\Strategies\RequestResponseArgs;

/** @var ContainerInterface $container */
$container = require __DIR__ . '/bootstrap.php';

/** @var Settings $settings */
$settings = $container->get(Settings::class);

$app = AppFactory::createFromContainer($container);

// Параметры маршрута ({id}) приходят в метод контроллера отдельными аргументами.
$app->getRouteCollector()->setDefaultInvocationStrategy(new RequestResponseArgs());

// Middleware в Slim выполняются в обратном порядке: последний добавленный — внешний.
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->add(ApiErrorMiddleware::class);
$errors = $app->addErrorMiddleware($settings->isDev(), true, true);
// Непредвиденные ошибки тоже в JSON, а не HTML-страницей Slim.
$errors->getDefaultErrorHandler()->forceContentType('application/json');

(require __DIR__ . '/routes.php')($app);

return $app;
