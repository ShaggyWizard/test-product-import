<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Psr\Container\ContainerInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

// В Docker переменные приходят из env_file; локальный .env — для запуска вне контейнера.
if (is_file(dirname(__DIR__) . '/.env')) {
    Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
}

return (static function (): ContainerInterface {
    $builder = new ContainerBuilder();
    $builder->useAutowiring(true);
    $builder->addDefinitions(__DIR__ . '/container.php');
    $builder->addDefinitions(__DIR__ . '/messenger.php');

    return $builder->build();
})();
