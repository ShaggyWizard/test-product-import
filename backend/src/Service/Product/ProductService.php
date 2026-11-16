<?php

declare(strict_types=1);

namespace App\Service\Product;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Ручные операции с товаром (CRUD). Импорт идёт отдельно, через ProductImporter.
 */
final class ProductService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $products,
    ) {
    }

    /**
     * @throws ProductConflictException товар с таким внешним кодом уже есть
     */
    public function create(ProductData $data): Product
    {
        $code = (string) $data->externalCode;
        if ($this->products->findOneByExternalCode($code) !== null) {
            throw new ProductConflictException(\sprintf('Товар с внешним кодом «%s» уже есть.', $code));
        }

        $product = new Product($code, (string) $data->name);
        $product
            ->setDescription($data->description)
            ->setPrice((string) $data->price)
            ->setDiscount($data->discount ?? '0.00');

        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }

    public function update(Product $product, ProductData $data): Product
    {
        if ($data->name !== null) {
            $product->setName($data->name);
        }
        if ($data->hasDescription) {
            $product->setDescription($data->description);
        }
        if ($data->price !== null) {
            $product->setPrice($data->price);
        }
        if ($data->discount !== null) {
            $product->setDiscount($data->discount);
        }

        $this->em->flush();

        return $product;
    }

    /**
     * Атрибуты и картинки удаляются каскадом (ON DELETE CASCADE в БД).
     */
    public function delete(Product $product): void
    {
        $this->em->remove($product);
        $this->em->flush();
    }
}
