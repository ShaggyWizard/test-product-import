<?php

declare(strict_types=1);

use App\Config\Settings;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Psr\Container\ContainerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

use function DI\factory;

return [
    Settings::class => factory(static fn (): Settings => Settings::fromEnv()),

    Connection::class => factory(static function (ContainerInterface $c): Connection {
        /** @var Settings $s */
        $s = $c->get(Settings::class);

        return DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => $s->dbHost,
            'port' => $s->dbPort,
            'dbname' => $s->dbName,
            'user' => $s->dbUser,
            'password' => $s->dbPassword,
            'charset' => 'utf8',
        ]);
    }),

    EntityManagerInterface::class => factory(static function (ContainerInterface $c): EntityManagerInterface {
        /** @var Settings $s */
        $s = $c->get(Settings::class);
        $root = dirname(__DIR__);

        $cache = $s->isDev()
            ? new ArrayAdapter()
            : new FilesystemAdapter('doctrine', 0, $root . '/var/cache');

        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [$root . '/src/Entity'],
            isDevMode: $s->isDev(),
            proxyDir: $root . '/var/cache/proxies',
            cache: $cache,
        );

        /** @var Connection $connection */
        $connection = $c->get(Connection::class);

        return new EntityManager($connection, $config);
    }),
];
