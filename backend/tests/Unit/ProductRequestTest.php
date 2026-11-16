<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Http\ProductRequest;
use App\Http\ValidationException;
use PHPUnit\Framework\TestCase;

final class ProductRequestTest extends TestCase
{
    public function testFilterDefaults(): void
    {
        $filter = (new ProductRequest())->filter([]);

        self::assertSame(1, $filter->page);
        self::assertSame(20, $filter->limit);
        self::assertNull($filter->name);
    }

    public function testFilterParsesAllParams(): void
    {
        $filter = (new ProductRequest())->filter(['page' => '3', 'limit' => '50', 'name' => ' бермуды ', 'priceMin' => '100', 'priceMax' => '1400,5']);

        self::assertSame(3, $filter->page);
        self::assertSame(50, $filter->limit);
        self::assertSame('бермуды', $filter->name);
        self::assertSame('100.00', $filter->priceMin);
        self::assertSame('1400.50', $filter->priceMax);
    }

    public function testFilterCollectsAllErrorsAtOnce(): void
    {
        try {
            (new ProductRequest())->filter(['page' => '0', 'limit' => '500', 'priceMin' => '10', 'priceMax' => '5']);
            self::fail('Ожидалась ValidationException');
        } catch (ValidationException $e) {
            self::assertSame(['page', 'limit', 'priceMax'], array_keys($e->errors));
        }
    }

    public function testUpdateForbidsChangingExternalCode(): void
    {
        $this->expectException(ValidationException::class);
        (new ProductRequest())->update(['externalCode' => 'NEW']);
    }

    public function testUpdateRequiresAtLeastOneField(): void
    {
        $this->expectException(ValidationException::class);
        (new ProductRequest())->update([]);
    }

    public function testUpdateAllowsClearingDescription(): void
    {
        $data = (new ProductRequest())->update(['description' => null]);

        self::assertTrue($data->hasDescription);
        self::assertNull($data->description);
    }
}
