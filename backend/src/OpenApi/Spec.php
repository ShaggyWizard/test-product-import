<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Общее описание API и схемы ответов. Пути описаны атрибутами прямо в контроллерах.
 */
#[OA\OpenApi(openapi: '3.1.0')]
#[OA\Info(
    version: '1.0.0',
    description: 'Импорт товаров из .xlsx (асинхронно через очередь), список и карточка товара.',
    title: 'Product Import API',
)]
#[OA\Server(url: '/', description: 'Текущий хост')]
#[OA\Tag(name: 'Products', description: 'Товары')]
#[OA\Tag(name: 'Import', description: 'Импорт из .xlsx')]
#[OA\Tag(name: 'System', description: 'Служебное')]
#[OA\Schema(
    schema: 'ProductListItem',
    required: ['id', 'externalCode', 'name', 'price', 'discount'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'externalCode', type: 'string', example: '3UHfAid1jaMiwgBuNvnsf3'),
        new OA\Property(property: 'name', type: 'string', example: 'Бермуды мужские, Grigio/Verde, OMSA, 46(M), Россия'),
        new OA\Property(property: 'price', type: 'number', example: 1320),
        new OA\Property(property: 'discount', description: 'Скидка, %', type: 'number', example: 33.33),
        new OA\Property(property: 'thumbnail', description: 'Адрес первой картинки', type: 'string', nullable: true),
    ],
)]
#[OA\Schema(
    schema: 'ProductPage',
    required: ['items', 'total', 'page', 'limit', 'totalPages'],
    properties: [
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/ProductListItem')),
        new OA\Property(property: 'total', type: 'integer', example: 40),
        new OA\Property(property: 'page', type: 'integer', example: 1),
        new OA\Property(property: 'limit', type: 'integer', example: 20),
        new OA\Property(property: 'totalPages', type: 'integer', example: 2),
    ],
)]
#[OA\Schema(
    schema: 'ProductAttribute',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'key', type: 'string', example: 'Цвет'),
        new OA\Property(property: 'value', type: 'string', example: 'Grigio/Verde'),
    ],
)]
#[OA\Schema(
    schema: 'ProductImage',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'url', description: 'Исходная ссылка из файла', type: 'string'),
        new OA\Property(property: 'path', description: 'Путь к скачанному файлу; null — не скачалось', type: 'string', nullable: true),
        new OA\Property(property: 'src', description: 'Адрес для показа', type: 'string'),
    ],
)]
#[OA\Schema(
    schema: 'Product',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'externalCode', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'price', type: 'number'),
        new OA\Property(property: 'discount', type: 'number'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'attributes', type: 'array', items: new OA\Items(ref: '#/components/schemas/ProductAttribute')),
        new OA\Property(property: 'images', type: 'array', items: new OA\Items(ref: '#/components/schemas/ProductImage')),
    ],
)]
#[OA\Schema(
    schema: 'ProductCreate',
    required: ['externalCode', 'name', 'price'],
    properties: [
        new OA\Property(property: 'externalCode', type: 'string', maxLength: 64),
        new OA\Property(property: 'name', type: 'string', maxLength: 255),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'price', type: 'number', exclusiveMinimum: 0),
        new OA\Property(property: 'discount', type: 'number', maximum: 100, minimum: 0),
    ],
)]
#[OA\Schema(
    schema: 'ProductUpdate',
    description: 'Только меняемые поля; externalCode менять нельзя',
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'price', type: 'number', exclusiveMinimum: 0),
        new OA\Property(property: 'discount', type: 'number', maximum: 100, minimum: 0),
    ],
)]
#[OA\Schema(
    schema: 'Error',
    properties: [new OA\Property(property: 'error', type: 'string', example: 'Товар не найден.')],
)]
#[OA\Schema(
    schema: 'ValidationError',
    properties: [
        new OA\Property(property: 'error', type: 'string', example: 'Некорректные данные запроса.'),
        new OA\Property(
            property: 'details',
            type: 'object',
            example: ['limit' => 'Целое число от 1 до 100.'],
            additionalProperties: new OA\AdditionalProperties(type: 'string'),
        ),
    ],
)]
final class Spec
{
}
