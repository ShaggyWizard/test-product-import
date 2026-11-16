<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;

/** @var ContainerInterface $container */
$container = require __DIR__ . '/bootstrap.php';

return (require __DIR__ . '/app_factory.php')($container);
