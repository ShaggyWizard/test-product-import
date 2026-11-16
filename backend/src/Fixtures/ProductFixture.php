<?php

declare(strict_types=1);

namespace App\Fixtures;

use App\Entity\Product;
use App\Service\Import\DiscountCalculator;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Тестовые данные для всех трёх таблиц: товары, их атрибуты и картинки.
 * Детерминированные (без случайности), чтобы тесты могли на них опираться.
 */
final class ProductFixture implements FixtureInterface
{
    public const COUNT = 30;

    private const KINDS = ['Колготки', 'Бандалетки', 'Бермуды', 'Носки', 'Леггинсы'];
    private const BRANDS = ['Collant', 'Caramello', 'MINIMI'];
    private const SIZES = ['2 (S)', '3 (M)', '4 (L)', '5 (XL)'];
    private const COLORS = ['nero', 'daino', 'bianco', 'fumo'];

    public function load(ObjectManager $manager): void
    {
        $calculator = new DiscountCalculator();

        for ($i = 1; $i <= self::COUNT; ++$i) {
            $kind = self::KINDS[$i % \count(self::KINDS)];
            $brand = self::BRANDS[$i % \count(self::BRANDS)];
            $size = self::SIZES[$i % \count(self::SIZES)];
            $color = self::COLORS[$i % \count(self::COLORS)];

            $price = number_format(500 + $i * 50, 2, '.', '');
            $purchase = number_format((500 + $i * 50) * 0.6, 2, '.', '');

            $product = new Product(\sprintf('SEED-%04d', $i), \sprintf('%s жен., %s, %s, %s', $kind, $brand, $size, $color));
            $product
                ->setDescription(\sprintf('Тестовый товар №%d для разработки и тестов.', $i))
                ->setPrice($price)
                ->setDiscount($calculator->calculate($price, $purchase));

            $product->syncAttributes([
                'Бренд' => $brand,
                'Размер' => $size,
                'Цвет' => $color,
                'Страна' => $i % 2 === 0 ? 'Италия' : 'Китай',
            ]);

            $product->syncImages([
                \sprintf('https://placehold.co/600x800/png?text=%s+%d', rawurlencode($kind), $i) => null,
            ]);

            $manager->persist($product);
        }

        $manager->flush();
    }
}
