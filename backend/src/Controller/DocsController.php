<?php

declare(strict_types=1);

namespace App\Controller;

use App\Config\Settings;
use OpenApi\Generator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * OpenAPI-спецификация (собирается из атрибутов в коде) и страница Swagger UI.
 */
final readonly class DocsController
{
    private const SWAGGER_UI = 'https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14';

    public function __construct(private Settings $settings) {}

    public function spec(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $root = \dirname(__DIR__, 2);
        $cache = $root . '/var/cache/openapi.json';

        // В prod спецификация собирается один раз, в dev — на каждый запрос, чтобы правки были видны сразу.
        if (!$this->settings->isDev() && is_file($cache)) {
            $json = (string) file_get_contents($cache);
        } else {
            $openapi = (new Generator())->generate([$root . '/src']);
            $json = $openapi === null ? '{}' : $openapi->toJson();
            if (!$this->settings->isDev()) {
                @mkdir(\dirname($cache), 0o775, true);
                @file_put_contents($cache, $json);
            }
        }

        $response->getBody()->write($json);

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public function ui(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $cdn = self::SWAGGER_UI;
        $response->getBody()->write(<<<HTML
            <!doctype html>
            <html lang="ru">
            <head>
              <meta charset="utf-8">
              <title>Product Import API</title>
              <link rel="stylesheet" href="{$cdn}/swagger-ui.css">
            </head>
            <body>
              <div id="swagger-ui"></div>
              <script src="{$cdn}/swagger-ui-bundle.js"></script>
              <script>SwaggerUIBundle({ url: '/api/openapi.json', dom_id: '#swagger-ui', deepLinking: true });</script>
            </body>
            </html>
            HTML);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
