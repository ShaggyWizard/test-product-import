<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Import\DiscountCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DiscountCalculatorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function cases(): iterable
    {
        // Пары цен из файла-примера: закупка везде 2/3 цены.
        yield 'пример 1320/880' => ['1320.00', '880.00', '33.33'];
        yield 'пример 1599/1066' => ['1599.00', '1066.00', '33.33'];
        yield 'без скидки' => ['100.00', '100.00', '0.00'];
        yield 'закупка выше продажи' => ['100.00', '120.00', '0.00'];
        yield 'цена 0 — без деления на ноль' => ['0.00', '10.00', '0.00'];
        yield 'закупка 0' => ['500.00', '0.00', '100.00'];
        yield 'копейки' => ['0.03', '0.01', '66.67'];
    }

    /**
     * @param numeric-string $sale
     * @param numeric-string $purchase
     */
    #[DataProvider('cases')]
    public function testCalculatesDiscountPercent(string $sale, string $purchase, string $expected): void
    {
        self::assertSame($expected, (new DiscountCalculator())->calculate($sale, $purchase));
    }
}
