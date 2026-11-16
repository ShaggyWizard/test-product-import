<?php

declare(strict_types=1);

use App\Config\Settings;
use Psr\Container\ContainerInterface;
use Slim\Factory\AppFactory;

/** @var ContainerInterface $container */
$container = require __DIR__ . '/bootstrap.php';

/** @var Settings $settings */
$settings = $container->get(Settings::class);

$app = AppFactory::createFromContainer($container);
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$errors = $app->addErrorMiddleware($settings->isDev(), true, true);
// API отвечает ошибками в JSON, а не HTML-страницей Slim.
$errors->getDefaultErrorHandler()->forceContentType('application/json');

(require __DIR__ . '/routes.php')($app);

return $app;
