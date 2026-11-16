<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Записывает один товар вместе с атрибутами и картинками в одной транзакции.
 * Upsert по external_code: есть товар — обновляем, нет — создаём.
 */
final class ProductImporter
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $products,
    ) {}

    /**
     * @param array<string, string|null> $imagePaths URL => локальный путь (null — не скачалось); картинки качаются до транзакции
     *
     * @return bool true — товар создан, false — обновлён
     */
    public function import(ParsedRow $row, array $imagePaths = []): bool
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();

        try {
            $product = $this->products->findOneByExternalCode($row->externalCode);
            $created = $product === null;

            if ($product === null) {
                $product = new Product($row->externalCode, $row->name);
                $this->em->persist($product);
            }

            $product
                ->setName($row->name)
                ->setDescription($row->description)
                ->setPrice($row->price)
                ->setDiscount($row->discount);
            $product->syncAttributes($row->attributes);
            // null — картинку не скачали: ссылка сохраняется, а у ранее скачанной путь не затирается.
            $urlToPath = [];
            foreach ($row->imageUrls as $url) {
                $urlToPath[$url] = $imagePaths[$url] ?? null;
            }
            $product->syncImages($urlToPath);

            // Товар, атрибуты и картинки уходят в БД разом: либо всё, либо ничего.
            $this->em->flush();
            $connection->commit();

            return $created;
        } catch (\Throwable $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $e;
        }
    }
}
