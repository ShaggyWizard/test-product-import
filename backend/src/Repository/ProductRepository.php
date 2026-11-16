<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @extends EntityRepository<Product>
 */
class ProductRepository extends EntityRepository
{
    public function findOneByExternalCode(string $externalCode): ?Product
    {
        return $this->findOneBy(['externalCode' => $externalCode]);
    }

    /**
     * Карточка: товар сразу с атрибутами и картинками одним запросом (без N+1).
     */
    public function findWithRelations(int $id): ?Product
    {
        /** @var Product|null $product */
        $product = $this->createQueryBuilder('p')
            ->addSelect('a', 'i')
            ->leftJoin('p.attributes', 'a')
            ->leftJoin('p.images', 'i')
            ->where('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        return $product;
    }

    /**
     * Серверная пагинация с поиском по названию и диапазоном цены.
     *
     * @return array{items: list<Product>, total: int}
     */
    public function search(ProductFilter $filter): array
    {
        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.id', 'ASC')
            ->setFirstResult(($filter->page - 1) * $filter->limit)
            ->setMaxResults($filter->limit);

        if ($filter->name !== null) {
            // ILIKE через LOWER: регистронезависимо и переносимо между MySQL и PostgreSQL.
            $qb->andWhere('LOWER(p.name) LIKE :name')
                ->setParameter('name', '%' . mb_strtolower(addcslashes($filter->name, '%_\\')) . '%');
        }
        if ($filter->priceMin !== null) {
            $qb->andWhere('p.price >= :priceMin')->setParameter('priceMin', $filter->priceMin);
        }
        if ($filter->priceMax !== null) {
            $qb->andWhere('p.price <= :priceMax')->setParameter('priceMax', $filter->priceMax);
        }

        $paginator = new Paginator($qb->getQuery(), fetchJoinCollection: false);

        /** @var list<Product> $items */
        $items = iterator_to_array($paginator->getIterator(), false);

        return ['items' => $items, 'total' => \count($paginator)];
    }
}
