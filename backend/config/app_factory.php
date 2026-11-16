<?php

declare(strict_types=1);

use App\Config\Settings;
use App\Http\ApiErrorMiddleware;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Handlers\ErrorHandler;
use Slim\Handlers\Strategies\RequestResponseArgs;

/**
 * Сборка Slim-приложения из контейнера. Её используют public/index.php и HTTP-тесты.
 *
 * @return \Closure(ContainerInterface): App<ContainerInterface|null>
 */
return static function (ContainerInterface $container): App {
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
    $handler = $errors->getDefaultErrorHandler();
    if ($handler instanceof ErrorHandler) {
        $handler->forceContentType('application/json');
    }

    (require __DIR__ . '/routes.php')($app);

    return $app;
};
