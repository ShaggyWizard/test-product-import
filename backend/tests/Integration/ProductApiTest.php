<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Command\SeedCommand;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * CRUD товаров и список с пагинацией/фильтрами через HTTP.
 */
final class ProductApiTest extends ApiTestCase
{
    public function testHealth(): void
    {
        $response = $this->request('GET', '/api/health');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ok', $this->json($response)['status']);
    }

    public function testCreateReadUpdateDelete(): void
    {
        // Create
        $created = $this->request('POST', '/api/products', [
            'externalCode' => 'CRUD-1', 'name' => 'Тестовый товар', 'price' => '1500,50', 'discount' => 10,
        ]);
        self::assertSame(201, $created->getStatusCode());
        $id = $this->json($created)['id'];
        self::assertSame('/api/products/' . $id, $created->getHeaderLine('Location'));

        // Read
        $read = $this->json($this->request('GET', '/api/products/' . $id));
        self::assertSame('Тестовый товар', $read['name']);
        self::assertSame(1500.5, $read['price']);

        // Update — только переданные поля
        $updated = $this->request('PUT', '/api/products/' . $id, ['price' => 1999.99, 'description' => 'Новое']);
        self::assertSame(200, $updated->getStatusCode());
        self::assertSame(1999.99, $this->json($updated)['price']);
        self::assertSame('Тестовый товар', $this->json($updated)['name']);

        // Delete
        self::assertSame(204, $this->request('DELETE', '/api/products/' . $id)->getStatusCode());
        self::assertSame(404, $this->request('GET', '/api/products/' . $id)->getStatusCode());
    }

    public function testDuplicateExternalCodeIsConflict(): void
    {
        $this->request('POST', '/api/products', ['externalCode' => 'DUP', 'name' => 'A', 'price' => 1]);
        $response = $this->request('POST', '/api/products', ['externalCode' => 'DUP', 'name' => 'B', 'price' => 2]);

        self::assertSame(409, $response->getStatusCode());
    }

    public function testValidationErrorsListFields(): void
    {
        $response = $this->request('POST', '/api/products', ['name' => '', 'price' => -5]);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(['externalCode', 'name', 'price'], array_keys($this->json($response)['details']));
    }

    public function testDeleteCascadesToAttributesAndImages(): void
    {
        (new CommandTester($this->service(SeedCommand::class)))->execute([]);
        self::assertSame(4, $this->rows('product_attributes WHERE product_id = 1'));

        $this->request('DELETE', '/api/products/1');

        self::assertSame(0, $this->rows('product_attributes WHERE product_id = 1'));
        self::assertSame(0, $this->rows('product_images WHERE product_id = 1'));
    }

    public function testSeedFillsAllThreeTables(): void
    {
        (new CommandTester($this->service(SeedCommand::class)))->execute([]);

        self::assertSame(30, $this->rows('products'));
        self::assertSame(120, $this->rows('product_attributes'));
        self::assertSame(30, $this->rows('product_images'));
    }

    public function testListIsPaginatedOnServer(): void
    {
        (new CommandTester($this->service(SeedCommand::class)))->execute([]);

        $page = $this->json($this->request('GET', '/api/products?page=2&limit=7'));

        self::assertSame(30, $page['total']);
        self::assertSame(5, $page['totalPages']);
        self::assertCount(7, $page['items']);
        self::assertSame('SEED-0008', $page['items'][0]['externalCode']);
        self::assertNotNull($page['items'][0]['thumbnail']);
    }

    public function testListFiltersByNameAndPriceRange(): void
    {
        (new CommandTester($this->service(SeedCommand::class)))->execute([]);

        // Цены сидера: 550, 600, … (500 + 50·i). «Бермуды» — i = 2, 7, 12, 17, 22, 27.
        $page = $this->json($this->request('GET', '/api/products?name=' . rawurlencode('БЕРМУДЫ') . '&priceMin=900&priceMax=1400'));

        self::assertSame(2, $page['total']);
        self::assertSame(['SEED-0012', 'SEED-0017'], array_column($page['items'], 'externalCode'));
    }

    public function testInvalidListParamsAre422(): void
    {
        $response = $this->request('GET', '/api/products?limit=500&priceMin=10&priceMax=5');

        self::assertSame(422, $response->getStatusCode());
    }
}
