<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Import\DiscountCalculator;
use App\Service\Import\RowMapper;
use PHPUnit\Framework\TestCase;

final class RowMapperTest extends TestCase
{
    private RowMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new RowMapper(new DiscountCalculator());
    }

    /**
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private function row(array $overrides = []): array
    {
        return $overrides + [
            'Внешний код' => 'ABC-1',
            'Наименование' => 'Бермуды мужские',
            'Описание' => 'Лёгкие',
            'Цена: Цена продажи' => '1 320,00',
            'Закупочная цена' => '880,00',
            'Доп. поле: Цвет' => 'Grigio/Verde',
            'Доп. поле: seo h1' => '',
            'Доп. поле: Ссылка на упаковку' => 'http://example.com/p.jpg',
            'Доп. поле: Ссылки на фото' => 'http://example.com/a.jpg, http://example.com/b.jpg, http://example.com/a.jpg',
            'НДС' => 'Без НДС',
        ];
    }

    public function testMapsValidRow(): void
    {
        $result = $this->mapper->map($this->row());

        self::assertSame([], $result->errors);
        self::assertNotNull($result->row);
        self::assertSame('ABC-1', $result->row->externalCode);
        self::assertSame('1320.00', $result->row->price, 'Запятая и пробел в цене понимаются');
        self::assertSame('33.33', $result->row->discount);
    }

    public function testAttributesComeFromExtraFieldsOnly(): void
    {
        $row = $this->mapper->map($this->row())->row;
        self::assertNotNull($row);

        // Пустое доп. поле не сохраняется, «Ссылки на фото» — не атрибут, «НДС» — не доп. поле.
        self::assertSame(
            ['Цвет' => 'Grigio/Verde', 'Ссылка на упаковку' => 'http://example.com/p.jpg'],
            $row->attributes,
        );
    }

    public function testPhotoLinksAreSplitAndDeduplicated(): void
    {
        $row = $this->mapper->map($this->row())->row;
        self::assertNotNull($row);

        self::assertSame(['http://example.com/a.jpg', 'http://example.com/b.jpg'], $row->imageUrls);
    }

    public function testInvalidLinksBecomeWarnings(): void
    {
        $result = $this->mapper->map($this->row(['Доп. поле: Ссылки на фото' => 'ftp://x/y.jpg, not-a-url, https://ok.ru/1.png']));

        self::assertNotNull($result->row);
        self::assertSame(['https://ok.ru/1.png'], $result->row->imageUrls);
        self::assertCount(2, $result->warnings);
    }

    public function testHeadersAreMatchedCaseAndSpaceInsensitive(): void
    {
        $result = $this->mapper->map([
            '  внешний   КОД ' => 'X',
            'НАИМЕНОВАНИЕ' => 'Товар',
            'цена: цена продажи' => '10',
        ]);

        self::assertNotNull($result->row);
        self::assertSame('X', $result->row->externalCode);
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function invalidRows(): iterable
    {
        yield 'пустой код' => [['Внешний код' => ''], 'Внешний код'];
        yield 'пустое имя' => [['Наименование' => ''], 'Наименование'];
        yield 'цена текстом' => [['Цена: Цена продажи' => 'сто рублей'], 'Цена: Цена продажи'];
        yield 'цена 0' => [['Цена: Цена продажи' => '0'], 'Цена: Цена продажи'];
        yield 'отрицательная цена' => [['Цена: Цена продажи' => '-5'], 'Цена: Цена продажи'];
        yield 'длинный код' => [['Внешний код' => str_repeat('x', 65)], 'Внешний код'];
    }

    /**
     * @param array<string, string> $overrides
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidRows')]
    public function testInvalidRowIsRejectedWithFieldName(array $overrides, string $field): void
    {
        $result = $this->mapper->map($this->row($overrides));

        self::assertNull($result->row);
        self::assertContains($field, array_map(static fn($e) => $e->field, $result->errors));
    }

    public function testMissingPurchasePriceGivesZeroDiscountAndWarning(): void
    {
        $result = $this->mapper->map($this->row(['Закупочная цена' => '']));

        self::assertNotNull($result->row);
        self::assertSame('0.00', $result->row->discount);
        self::assertCount(1, $result->warnings);
    }

    public function testReportsMissingRequiredColumns(): void
    {
        self::assertSame(
            ['Цена: Цена продажи'],
            $this->mapper->missingColumns(['Внешний код', 'Наименование', 'Описание']),
        );
    }
}
