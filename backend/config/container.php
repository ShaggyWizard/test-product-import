<?php

declare(strict_types=1);

use App\Config\Settings;
use App\Entity\ImportJob;
use App\Entity\Product;
use App\Repository\ImportJobRepository;
use App\Repository\ProductRepository;
use App\Service\Import\ImageDownloader;
use App\Service\Import\UploadStorage;
use Doctrine\DBAL\Configuration as DbalConfiguration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;

use function DI\factory;

return [
    Settings::class => factory(static fn (): Settings => Settings::fromEnv()),

    Connection::class => factory(static function (ContainerInterface $c): Connection {
        /** @var Settings $s */
        $s = $c->get(Settings::class);

        // Служебную таблицу миграций не сравниваем со схемой сущностей.
        $config = new DbalConfiguration();
        $config->setSchemaAssetsFilter(
            static fn (string|AbstractAsset $asset): bool => ($asset instanceof AbstractAsset ? $asset->getName() : $asset)
                !== 'doctrine_migration_versions',
        );

        return DriverManager::getConnection([
            'driver' => 'pdo_pgsql',
            'host' => $s->dbHost,
            'port' => $s->dbPort,
            'dbname' => $s->dbName,
            'user' => $s->dbUser,
            'password' => $s->dbPassword,
            'charset' => 'utf8',
        ], $config);
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

    ResponseFactoryInterface::class => factory(static fn (): ResponseFactoryInterface => new ResponseFactory()),

    ImageDownloader::class => factory(static function (Settings $s): ImageDownloader {
        $http = new NoPrivateNetworkHttpClient(HttpClient::create([
            'timeout' => $s->imageDownloadTimeout,
            'max_duration' => $s->imageDownloadTimeout * 3,
            'max_redirects' => 3,
            'headers' => ['User-Agent' => 'product-import/1.0', 'Accept' => 'image/*'],
        ]));

        return new ImageDownloader($http, dirname(__DIR__) . '/public', $s->imageMaxSize);
    }),

    UploadStorage::class => factory(
        static fn (): UploadStorage => new UploadStorage(dirname(__DIR__) . '/var/uploads'),
    ),

    // Репозитории берём у EntityManager, чтобы Doctrine сама их собрала.
    ProductRepository::class => factory(
        static fn (EntityManagerInterface $em): ProductRepository => $em->getRepository(Product::class),
    ),
    ImportJobRepository::class => factory(
        static fn (EntityManagerInterface $em): ImportJobRepository => $em->getRepository(ImportJob::class),
    ),
];
