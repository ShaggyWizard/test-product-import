<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Service\Import\ImageDownloader;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

use function DI\factory;

/**
 * Контейнер приложения с тестовыми подменами: картинки отдаёт MockHttpClient (без сети),
 * файлы пишутся во временный каталог, логи молчат.
 */
final class TestContainer
{
    /** Минимальный валидный PNG 1×1. */
    public const PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89"
        . "\x00\x00\x00\rIDATx\x9cc\xf8\xff\xff?\x00\x05\xfe\x02\xfe\xa7\x35\x81\x84\x00\x00\x00\x00IEND\xaeB`\x82";

    public static function mediaDir(): string
    {
        return sys_get_temp_dir() . '/product-import-tests';
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function build(array $overrides = []): ContainerInterface
    {
        $config = dirname(__DIR__, 2) . '/config';

        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->addDefinitions($config . '/container.php');
        $builder->addDefinitions($config . '/messenger.php');
        $builder->addDefinitions([
            LoggerInterface::class => factory(static fn (): LoggerInterface => new NullLogger()),
            // Ссылка, где есть «broken», отвечает 404; остальные — PNG.
            ImageDownloader::class => factory(static fn (): ImageDownloader => new ImageDownloader(
                new MockHttpClient(static fn (string $method, string $url): MockResponse => str_contains($url, 'broken')
                    ? new MockResponse('', ['http_code' => 404])
                    : new MockResponse(self::PNG, ['response_headers' => ['content-type' => 'image/png']])),
                self::mediaDir(),
                1024 * 1024,
            )),
        ]);
        $builder->addDefinitions($overrides);

        return $builder->build();
    }
}
