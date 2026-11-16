<?php

declare(strict_types=1);

namespace App\Service\Import;

/**
 * Превращает строку файла в ParsedRow: находит столбцы по названию, приводит цены,
 * считает скидку, собирает атрибуты и ссылки на картинки.
 * Ошибки возвращает списком, а не исключением: одна плохая строка не должна ронять импорт.
 */
final class RowMapper
{
    private const COL_CODE = 'внешний код';
    private const COL_NAME = 'наименование';
    private const COL_DESCRIPTION = 'описание';
    private const COL_PRICE = 'цена: цена продажи';
    private const COL_PURCHASE = 'закупочная цена';

    /** Столбец с картинками: идёт в product_images, а не в атрибуты. */
    private const ATTR_PHOTOS = 'ссылки на фото';
    private const ATTR_PATTERN = '/^доп\.\s*поле\s*:?\s*(.+)$/ui';

    private const REQUIRED = [
        self::COL_CODE => 'Внешний код',
        self::COL_NAME => 'Наименование',
        self::COL_PRICE => 'Цена: Цена продажи',
    ];

    private const MAX_CODE = 64;
    private const MAX_NAME = 255;
    private const MAX_KEY = 255;
    private const MAX_URL = 2048;

    public function __construct(private readonly DiscountCalculator $discounts)
    {
    }

    /**
     * @param list<string> $headers
     *
     * @return list<string> названия обязательных столбцов, которых нет в файле
     */
    public function missingColumns(array $headers): array
    {
        $present = array_map($this->normalize(...), $headers);

        return array_values(array_filter(
            self::REQUIRED,
            static fn (string $title, string $key): bool => !\in_array($key, $present, true),
            \ARRAY_FILTER_USE_BOTH,
        ));
    }

    /**
     * @param array<string, string> $cells заголовок => значение
     */
    public function map(array $cells): MappedRow
    {
        $errors = [];
        $warnings = [];
        $fields = [];
        $attributes = [];
        $photos = '';

        foreach ($cells as $header => $value) {
            $header = (string) $header;
            if (preg_match(self::ATTR_PATTERN, $header, $m) === 1) {
                $key = trim($m[1]);
                if ($this->normalize($key) === self::ATTR_PHOTOS) {
                    $photos = $value;
                } elseif ($value !== '') {
                    // Пустые доп. поля не храним: в файле их большинство.
                    if (mb_strlen($key) > self::MAX_KEY) {
                        $warnings[] = new RowIssue($header, 'Название доп. поля длиннее 255 символов, пропущено.');
                    } else {
                        $attributes[$key] = $value;
                    }
                }
                continue;
            }
            $fields[$this->normalize($header)] ??= $value;
        }

        $code = $fields[self::COL_CODE] ?? '';
        $name = $fields[self::COL_NAME] ?? '';
        $description = $fields[self::COL_DESCRIPTION] ?? '';

        if ($code === '') {
            $errors[] = new RowIssue('Внешний код', 'Пустой внешний код.');
        } elseif (mb_strlen($code) > self::MAX_CODE) {
            $errors[] = new RowIssue('Внешний код', 'Внешний код длиннее 64 символов.');
        }

        if ($name === '') {
            $errors[] = new RowIssue('Наименование', 'Пустое наименование.');
        } elseif (mb_strlen($name) > self::MAX_NAME) {
            $errors[] = new RowIssue('Наименование', 'Наименование длиннее 255 символов.');
        }

        $rawPrice = $fields[self::COL_PRICE] ?? '';
        $price = $this->parseMoney($rawPrice);
        if ($price === null) {
            $errors[] = new RowIssue(
                'Цена: Цена продажи',
                $rawPrice === '' ? 'Пустая цена продажи.' : \sprintf('Цена продажи «%s» не является числом.', $rawPrice),
            );
        } elseif ((float) $price <= 0.0) {
            $errors[] = new RowIssue('Цена: Цена продажи', 'Цена продажи должна быть больше нуля.');
            $price = null;
        }

        $discount = '0.00';
        if ($price !== null) {
            $rawPurchase = $fields[self::COL_PURCHASE] ?? '';
            $purchase = $this->parseMoney($rawPurchase);
            if ($purchase === null) {
                $warnings[] = new RowIssue(
                    'Закупочная цена',
                    $rawPurchase === ''
                        ? 'Нет закупочной цены, скидка записана как 0.'
                        : \sprintf('Закупочная цена «%s» не является числом, скидка записана как 0.', $rawPurchase),
                );
            } else {
                $discount = $this->discounts->calculate($price, $purchase);
            }
        }

        $imageUrls = $this->parseUrls($photos, $warnings);

        if ($errors !== [] || $price === null) {
            return new MappedRow(null, $code === '' ? null : $code, $errors, $warnings);
        }

        return new MappedRow(
            new ParsedRow($code, $name, $description === '' ? null : $description, $price, $discount, $attributes, $imageUrls),
            $code,
            [],
            $warnings,
        );
    }

    /**
     * «1 320,00» → «1320.00». Цены в файле лежат текстом с десятичной запятой.
     *
     * @return numeric-string|null null, если это не неотрицательное число
     */
    private function parseMoney(string $raw): ?string
    {
        $value = str_replace(',', '.', (string) preg_replace('/[\s\x{00A0}]+/u', '', $raw));

        // До 10 цифр целой части: столько вмещает NUMERIC(12,2).
        if (preg_match('/^\d{1,10}(\.\d+)?$/', $value) !== 1) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    /**
     * В ячейке несколько ссылок через запятую. Одинаковые ссылки схлопываются.
     *
     * @param list<RowIssue> $warnings
     *
     * @return list<string>
     */
    private function parseUrls(string $raw, array &$warnings): array
    {
        $urls = [];
        foreach (preg_split('/[\s,;]+/u', $raw, -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $url) {
            $scheme = strtolower((string) parse_url($url, \PHP_URL_SCHEME));
            if (!\in_array($scheme, ['http', 'https'], true) || filter_var($url, \FILTER_VALIDATE_URL) === false || \strlen($url) > self::MAX_URL) {
                $warnings[] = new RowIssue('Доп. поле: Ссылки на фото', \sprintf('Ссылка «%s» некорректна, пропущена.', mb_strimwidth($url, 0, 100, '…')));
                continue;
            }
            $urls[$url] = true;
        }

        return array_map(strval(...), array_keys($urls));
    }

    private function normalize(string $header): string
    {
        return mb_strtolower(trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $header)));
    }
}
