<?php

declare(strict_types=1);

namespace App\Service\Import;

/**
 * Проверенная строка файла, готовая к записи в БД.
 */
final readonly class ParsedRow
{
    /**
     * @param numeric-string        $price
     * @param numeric-string        $discount
     * @param array<string, string> $attributes ключ => значение
     * @param list<string>          $imageUrls
     */
    public function __construct(
        public string $externalCode,
        public string $name,
        public ?string $description,
        public string $price,
        public string $discount,
        public array $attributes,
        public array $imageUrls,
    ) {
    }
}
