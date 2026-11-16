<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UploadedFileInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * HTTP-запросы к приложению в том же процессе, без веб-сервера: тот же роутинг, middleware и контроллеры.
 */
abstract class ApiTestCase extends IntegrationTestCase
{
    /** @var App<\Psr\Container\ContainerInterface|null> */
    private App $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = (require \dirname(__DIR__, 2) . '/config/app_factory.php')($this->container);
    }

    /**
     * @param array<string, mixed>|null                $json
     * @param array<string, UploadedFileInterface>      $files
     */
    protected function request(string $method, string $uri, ?array $json = null, array $files = []): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $uri, ['REMOTE_ADDR' => '10.0.0.1']);
        if ($json !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody((new StreamFactory())->createStream(json_encode($json, \JSON_THROW_ON_ERROR)));
        }
        if ($files !== []) {
            $request = $request->withUploadedFiles($files);
        }

        return $this->app->handle($request);
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(ResponseInterface $response): array
    {
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getBody(), true, 512, \JSON_THROW_ON_ERROR);

        return $data;
    }
}
