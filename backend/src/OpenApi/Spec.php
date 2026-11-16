<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Общее описание API и схемы ответов. Пути описаны атрибутами прямо в контроллерах.
 */
#[OA\OpenApi(openapi: '3.1.0', security: [['bearerAuth' => []]])]
#[OA\Info(
    version: '1.0.0',
    description: 'Импорт товаров из .xlsx (асинхронно через очередь), список и карточка товара. '
        . 'Все методы, кроме входа, health и документации, требуют JWT: получите его в POST /api/auth/login '
        . 'и нажмите Authorize.',
    title: 'Product Import API',
)]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', bearerFormat: 'JWT', scheme: 'bearer')]
#[OA\Server(url: '/', description: 'Текущий хост')]
#[OA\Tag(name: 'Auth', description: 'Вход')]
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
    schema: 'ImportIssue',
    properties: [
        new OA\Property(property: 'row', description: 'Номер строки в файле', type: 'integer', example: 4),
        new OA\Property(property: 'externalCode', type: 'string', nullable: true),
        new OA\Property(property: 'field', description: 'Столбец', type: 'string', nullable: true, example: 'Цена: Цена продажи'),
        new OA\Property(property: 'message', type: 'string', example: 'Цена продажи «сто рублей» не является числом.'),
    ],
)]
#[OA\Schema(
    schema: 'ImportJob',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'running', 'done', 'failed']),
        new OA\Property(property: 'fileName', type: 'string'),
        new OA\Property(property: 'totalRows', type: 'integer'),
        new OA\Property(property: 'processedRows', type: 'integer'),
        new OA\Property(property: 'progress', description: 'Процент, 0–100', type: 'integer'),
        new OA\Property(property: 'created', type: 'integer'),
        new OA\Property(property: 'updated', type: 'integer'),
        new OA\Property(property: 'errors', description: 'Строки, которые не записаны', type: 'array', items: new OA\Items(ref: '#/components/schemas/ImportIssue')),
        new OA\Property(property: 'warnings', description: 'Записано, но с замечаниями', type: 'array', items: new OA\Items(ref: '#/components/schemas/ImportIssue')),
        new OA\Property(property: 'failureReason', type: 'string', nullable: true),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'startedAt', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'finishedAt', type: 'string', format: 'date-time', nullable: true),
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
final class Spec {}
