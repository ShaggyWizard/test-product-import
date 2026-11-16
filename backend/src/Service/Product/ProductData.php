<?php

declare(strict_types=1);

namespace App\Service\Product;

/**
 * Проверенные данные для создания или изменения товара. null — поле не передавали.
 */
final readonly class ProductData
{
    /**
     * @param numeric-string|null $price
     * @param numeric-string|null $discount
     */
    public function __construct(
        public ?string $externalCode,
        public ?string $name,
        public ?string $description,
        public ?string $price,
        public ?string $discount,
        public bool $hasDescription,
    ) {}
}
