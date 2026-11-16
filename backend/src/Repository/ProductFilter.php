<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Параметры списка товаров: страница, размер страницы, поиск и диапазон цены.
 */
final readonly class ProductFilter
{
    public const DEFAULT_LIMIT = 20;
    public const MAX_LIMIT = 100;

    public function __construct(
        public int $page = 1,
        public int $limit = self::DEFAULT_LIMIT,
        public ?string $name = null,
        public ?string $priceMin = null,
        public ?string $priceMax = null,
    ) {}
}
