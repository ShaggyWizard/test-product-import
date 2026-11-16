<?php

declare(strict_types=1);

namespace App\Http;

use App\Entity\Product;
use App\Entity\ProductAttribute;
use App\Entity\ProductImage;

/**
 * Превращает сущности в JSON-ответ API. Сущности наружу не отдаём: формат ответа не зависит от схемы БД.
 */
final class ProductPresenter
{
    /**
     * Строка списка: без описания и атрибутов, с одной картинкой-превью.
     *
     * @return array<string, mixed>
     */
    public function listItem(Product $product, ?ProductImage $thumbnail): array
    {
        return [
            'id' => $product->getId(),
            'externalCode' => $product->getExternalCode(),
            'name' => $product->getName(),
            'price' => (float) $product->getPrice(),
            'discount' => (float) $product->getDiscount(),
            'thumbnail' => $thumbnail === null ? null : $this->imageSrc($thumbnail),
        ];
    }

    /**
     * Карточка: все поля, атрибуты и картинки.
     *
     * @return array<string, mixed>
     */
    public function detail(Product $product): array
    {
        return [
            'id' => $product->getId(),
            'externalCode' => $product->getExternalCode(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => (float) $product->getPrice(),
            'discount' => (float) $product->getDiscount(),
            'createdAt' => $product->getCreatedAt()->format(\DATE_ATOM),
            'updatedAt' => $product->getUpdatedAt()->format(\DATE_ATOM),
            'attributes' => array_values($product->getAttributes()->map(
                static fn(ProductAttribute $a): array => ['id' => $a->getId(), 'key' => $a->getKey(), 'value' => $a->getValue()],
            )->toArray()),
            'images' => array_values($product->getImages()->map(
                fn(ProductImage $i): array => [
                    'id' => $i->getId(),
                    'url' => $i->getUrl(),
                    'path' => $i->getPath(),
                    'src' => $this->imageSrc($i),
                ],
            )->toArray()),
        ];
    }

    /**
     * Адрес для <img>: скачанный файл, а если скачать не удалось — исходная ссылка.
     */
    private function imageSrc(ProductImage $image): string
    {
        $path = $image->getPath();

        return $path === null ? $image->getUrl() : '/' . ltrim($path, '/');
    }
}
