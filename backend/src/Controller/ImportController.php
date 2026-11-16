<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\ImportJobPresenter;
use App\Http\JsonResponder;
use App\Repository\ImportJobRepository;
use App\Service\Import\ImportStarter;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Exception\HttpNotFoundException;
use Symfony\Component\Uid\Uuid;

final readonly class ImportController
{
    public function __construct(
        private ImportStarter $starter,
        private ImportJobRepository $jobs,
        private ImportJobPresenter $presenter,
        private JsonResponder $json,
    ) {}

    #[OA\Post(
        path: '/api/import',
        summary: 'Загрузить .xlsx и поставить импорт в очередь',
        description: 'Файл проверяется (расширение, размер, тип по содержимому), сохраняется, задача уходит в RabbitMQ. '
            . 'Ответ приходит сразу; ход импорта — GET /api/import/{id}. Не больше IMPORT_RATE_LIMIT загрузок в окно с одного IP.',
        tags: ['Import'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file'],
                    properties: [new OA\Property(property: 'file', type: 'string', format: 'binary')],
                ),
            ),
        ),
        responses: [
            new OA\Response(response: 202, description: 'Принято в очередь', content: new OA\JsonContent(ref: '#/components/schemas/ImportJob')),
            new OA\Response(response: 422, description: 'Файл не подходит', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 429, description: 'Слишком много импортов', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function start(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $file = $request->getUploadedFiles()['file'] ?? null;
        $job = $this->starter->start($file instanceof UploadedFileInterface ? $file : null);

        return $this->json->send($response, $this->presenter->present($job), 202)
            ->withHeader('Location', '/api/import/' . $job->getId());
    }

    #[OA\Get(
        path: '/api/import/{id}',
        summary: 'Статус импорта: прогресс и отчёт об ошибках (для polling)',
        tags: ['Import'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Задача импорта', content: new OA\JsonContent(ref: '#/components/schemas/ImportJob')),
            new OA\Response(response: 404, description: 'Задача не найдена', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function show(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $job = Uuid::isValid($id) ? $this->jobs->find($id) : null;
        if ($job === null) {
            throw new HttpNotFoundException($request, 'Задача импорта не найдена.');
        }

        // Статус меняется каждую секунду: кэшировать ответ нельзя.
        return $this->json->send($response, $this->presenter->present($job))
            ->withHeader('Cache-Control', 'no-store');
    }

    #[OA\Get(
        path: '/api/import/latest',
        summary: 'Последний импорт (для блока «Последний импорт»)',
        tags: ['Import'],
        responses: [
            new OA\Response(response: 200, description: 'Задача импорта', content: new OA\JsonContent(ref: '#/components/schemas/ImportJob')),
            new OA\Response(response: 404, description: 'Импортов ещё не было', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function latest(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $job = $this->jobs->findLatest();
        if ($job === null) {
            throw new HttpNotFoundException($request, 'Импортов ещё не было.');
        }

        return $this->json->send($response, $this->presenter->present($job))
            ->withHeader('Cache-Control', 'no-store');
    }
}
