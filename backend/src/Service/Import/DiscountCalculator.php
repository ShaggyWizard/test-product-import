<?php

declare(strict_types=1);

namespace App\Service\Import;

/**
 * Скидка, % = (цена продажи − закупочная цена) / цена продажи × 100.
 * Цены переводятся в целые копейки, поэтому ошибки float не влияют на результат.
 */
final class DiscountCalculator
{
    /**
     * @param numeric-string $salePrice
     * @param numeric-string $purchasePrice
     *
     * @return numeric-string скидка с двумя знаками, например "33.33"
     */
    public function calculate(string $salePrice, string $purchasePrice): string
    {
        $sale = (float) $salePrice;
        $purchase = (float) $purchasePrice;

        // Цена 0 — делить нельзя; закупка выше продажи — скидки нет.
        if ($sale <= 0.0 || $purchase >= $sale) {
            return '0.00';
        }

        // Работаем в копейках (целые), чтобы округление было предсказуемым.
        $saleCents = (int) round($sale * 100);
        $purchaseCents = (int) round($purchase * 100);
        $discount = ($saleCents - $purchaseCents) * 100 / $saleCents;

        return number_format(round($discount, 2), 2, '.', '');
    }
}
