<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Product;
use App\Http\JsonResponder;
use App\Http\ProductPresenter;
use App\Http\ProductRequest;
use App\Repository\ProductRepository;
use App\Service\Product\ProductService;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;

/**
 * Тонкий контроллер: разобрать запрос → вызвать репозиторий или сервис → отдать JSON.
 */
final readonly class ProductController
{
    public function __construct(
        private ProductRepository $products,
        private ProductService $service,
        private ProductRequest $input,
        private ProductPresenter $presenter,
        private JsonResponder $json,
    ) {
    }

    #[OA\Get(
        path: '/api/products',
        summary: 'Список товаров с серверной пагинацией, поиском и фильтром по цене',
        tags: ['Products'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20, maximum: 100, minimum: 1)),
            new OA\Parameter(name: 'name', in: 'query', description: 'Поиск по названию, без учёта регистра', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'priceMin', in: 'query', schema: new OA\Schema(type: 'number', minimum: 0)),
            new OA\Parameter(name: 'priceMax', in: 'query', schema: new OA\Schema(type: 'number', minimum: 0)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Страница товаров', content: new OA\JsonContent(ref: '#/components/schemas/ProductPage')),
            new OA\Response(response: 422, description: 'Некорректные параметры', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $filter = $this->input->filter($request->getQueryParams());
        $page = $this->products->search($filter);

        $ids = array_map(static fn (Product $p): int => (int) $p->getId(), $page['items']);
        $thumbnails = $this->products->firstImages($ids);

        return $this->json->send($response, [
            'items' => array_map(
                fn (Product $p): array => $this->presenter->listItem($p, $thumbnails[(int) $p->getId()] ?? null),
                $page['items'],
            ),
            'total' => $page['total'],
            'page' => $filter->page,
            'limit' => $filter->limit,
            'totalPages' => (int) ceil($page['total'] / $filter->limit),
        ]);
    }

    #[OA\Get(
        path: '/api/products/{id}',
        summary: 'Карточка товара: поля, атрибуты, картинки',
        tags: ['Products'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Товар', content: new OA\JsonContent(ref: '#/components/schemas/Product')),
            new OA\Response(response: 404, description: 'Товар не найден', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function show(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $product = $this->products->findWithRelations($this->id($request, $id));
        if ($product === null) {
            throw new HttpNotFoundException($request, 'Товар не найден.');
        }

        return $this->json->send($response, $this->presenter->detail($product));
    }

    #[OA\Post(
        path: '/api/products',
        summary: 'Создать товар вручную',
        tags: ['Products'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ProductCreate')),
        responses: [
            new OA\Response(response: 201, description: 'Создан', content: new OA\JsonContent(ref: '#/components/schemas/Product')),
            new OA\Response(response: 409, description: 'Внешний код уже занят', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 422, description: 'Некорректные данные', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $product = $this->service->create($this->input->create($request->getParsedBody()));

        return $this->json->send($response, $this->presenter->detail($product), 201)
            ->withHeader('Location', '/api/products/' . $product->getId());
    }

    #[OA\Put(
        path: '/api/products/{id}',
        summary: 'Изменить товар: передаются только меняемые поля',
        tags: ['Products'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ProductUpdate')),
        responses: [
            new OA\Response(response: 200, description: 'Изменён', content: new OA\JsonContent(ref: '#/components/schemas/Product')),
            new OA\Response(response: 404, description: 'Товар не найден', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 422, description: 'Некорректные данные', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function update(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $product = $this->products->findWithRelations($this->id($request, $id));
        if ($product === null) {
            throw new HttpNotFoundException($request, 'Товар не найден.');
        }

        $product = $this->service->update($product, $this->input->update($request->getParsedBody()));

        return $this->json->send($response, $this->presenter->detail($product));
    }

    #[OA\Delete(
        path: '/api/products/{id}',
        summary: 'Удалить товар вместе с атрибутами и картинками',
        tags: ['Products'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Удалён'),
            new OA\Response(response: 404, description: 'Товар не найден', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function delete(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $product = $this->products->find($this->id($request, $id));
        if ($product === null) {
            throw new HttpNotFoundException($request, 'Товар не найден.');
        }

        $this->service->delete($product);

        return $response->withStatus(204);
    }

    private function id(ServerRequestInterface $request, string $id): int
    {
        $value = filter_var($id, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) {
            throw new HttpNotFoundException($request, 'Товар не найден.');
        }

        return $value;
    }
}
