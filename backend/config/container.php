<?php

declare(strict_types=1);

use App\Config\Settings;
use App\Entity\ImportJob;
use App\Entity\Product;
use App\Http\JsonResponder;
use App\Http\RateLimitMiddleware;
use App\Logging\StderrLogger;
use App\Repository\ImportJobRepository;
use App\Repository\ProductRepository;
use App\Service\Import\ImageDownloader;
use App\Service\Import\ImportUploadValidator;
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
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

use function DI\factory;

return [
    Settings::class => factory(static fn(): Settings => Settings::fromEnv()),

    Connection::class => factory(static function (ContainerInterface $c): Connection {
        /** @var Settings $s */
        $s = $c->get(Settings::class);

        // Служебную таблицу миграций не сравниваем со схемой сущностей.
        $config = new DbalConfiguration();
        $config->setSchemaAssetsFilter(
            static fn(string|AbstractAsset $asset): bool => ($asset instanceof AbstractAsset ? $asset->getName() : $asset)
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

    ResponseFactoryInterface::class => factory(static fn(): ResponseFactoryInterface => new ResponseFactory()),

    LoggerInterface::class => factory(static fn(): LoggerInterface => new StderrLogger()),

    // Лимит на импорт: N загрузок за окно с одного IP. Хранилище — файловый кэш (запросы принимает один контейнер app).
    'rate_limiter.import' => factory(static fn(Settings $s): RateLimiterFactory => new RateLimiterFactory(
        [
            'id' => 'import',
            'policy' => 'sliding_window',
            'limit' => $s->importRateLimit,
            'interval' => $s->importRateInterval,
        ],
        new CacheStorage(new FilesystemAdapter('rate_limiter', 0, dirname(__DIR__) . '/var/cache')),
    )),

    RateLimitMiddleware::class => factory(static fn(ContainerInterface $c): RateLimitMiddleware => new RateLimitMiddleware(
        $c->get('rate_limiter.import'),
        $c->get(ResponseFactoryInterface::class),
        $c->get(JsonResponder::class),
    )),

    ImportUploadValidator::class => factory(
        static fn(Settings $s): ImportUploadValidator => new ImportUploadValidator($s->importMaxFileSize),
    ),

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
        static fn(): UploadStorage => new UploadStorage(dirname(__DIR__) . '/var/uploads'),
    ),

    // Репозитории берём у EntityManager, чтобы Doctrine сама их собрала.
    ProductRepository::class => factory(static function (EntityManagerInterface $em): ProductRepository {
        $repository = $em->getRepository(Product::class);
        assert($repository instanceof ProductRepository);

        return $repository;
    }),
    ImportJobRepository::class => factory(static function (EntityManagerInterface $em): ImportJobRepository {
        $repository = $em->getRepository(ImportJob::class);
        assert($repository instanceof ImportJobRepository);

        return $repository;
    }),
];
