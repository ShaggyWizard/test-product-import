<?php

declare(strict_types=1);

namespace App\Http;

use App\Repository\ProductFilter;
use App\Service\Product\ProductData;

/**
 * Разбор и проверка входных данных API товаров. Все ошибки собираются разом, а не по одной.
 */
final class ProductRequest
{
    /**
     * Параметры списка: ?page=&limit=&name=&priceMin=&priceMax=
     *
     * @param array<string, mixed> $query
     */
    public function filter(array $query): ProductFilter
    {
        $errors = [];

        $page = $this->int($query, 'page', 1, 1, \PHP_INT_MAX, $errors);
        $limit = $this->int($query, 'limit', ProductFilter::DEFAULT_LIMIT, 1, ProductFilter::MAX_LIMIT, $errors);

        $name = $this->string($query, 'name');
        if ($name !== null && mb_strlen($name) > 255) {
            $errors['name'] = 'Не длиннее 255 символов.';
        }

        $priceMin = $this->money($query, 'priceMin', $errors);
        $priceMax = $this->money($query, 'priceMax', $errors);
        if ($priceMin !== null && $priceMax !== null && (float) $priceMin > (float) $priceMax) {
            $errors['priceMax'] = 'Должна быть не меньше priceMin.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new ProductFilter($page, $limit, $name, $priceMin, $priceMax);
    }

    /**
     * Тело POST (создание): обязательны externalCode, name, price.
     *
     * @param array<string, mixed>|object|null $body
     */
    public function create(array|object|null $body): ProductData
    {
        $body = \is_array($body) ? $body : [];
        $errors = [];

        $code = $this->string($body, 'externalCode');
        if ($code === null) {
            $errors['externalCode'] = 'Обязательное поле.';
        } elseif (mb_strlen($code) > 64) {
            $errors['externalCode'] = 'Не длиннее 64 символов.';
        }

        $data = $this->fields($body, $errors, required: true);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new ProductData($code, $data['name'], $data['description'], $data['price'], $data['discount'], $data['hasDescription']);
    }

    /**
     * Тело PUT (изменение): передаются только меняемые поля. externalCode менять нельзя — это ключ импорта.
     *
     * @param array<string, mixed>|object|null $body
     */
    public function update(array|object|null $body): ProductData
    {
        $body = \is_array($body) ? $body : [];
        $errors = [];

        if (\array_key_exists('externalCode', $body)) {
            $errors['externalCode'] = 'Внешний код менять нельзя: по нему работает повторный импорт.';
        }

        $data = $this->fields($body, $errors, required: false);

        if ($errors === [] && $data['name'] === null && !$data['hasDescription'] && $data['price'] === null && $data['discount'] === null) {
            $errors['body'] = 'Нужно передать хотя бы одно поле: name, description, price, discount.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new ProductData(null, $data['name'], $data['description'], $data['price'], $data['discount'], $data['hasDescription']);
    }

    /**
     * @param array<string, mixed>  $body
     * @param array<string, string> $errors
     *
     * @return array{name: ?string, description: ?string, hasDescription: bool, price: ?numeric-string, discount: ?numeric-string}
     */
    private function fields(array $body, array &$errors, bool $required): array
    {
        $name = $this->string($body, 'name');
        if ($name === null && ($required || \array_key_exists('name', $body))) {
            $errors['name'] = 'Обязательное поле, не пустое.';
        } elseif ($name !== null && mb_strlen($name) > 255) {
            $errors['name'] = 'Не длиннее 255 символов.';
        }

        $hasDescription = \array_key_exists('description', $body);
        $description = $this->string($body, 'description');
        if ($hasDescription && $body['description'] !== null && !\is_string($body['description'])) {
            $errors['description'] = 'Должно быть строкой или null.';
        }

        $price = $this->money($body, 'price', $errors);
        if ($price === null && ($required || \array_key_exists('price', $body)) && !isset($errors['price'])) {
            $errors['price'] = 'Обязательное поле.';
        } elseif ($price !== null && (float) $price <= 0.0) {
            $errors['price'] = 'Должна быть больше нуля.';
        }

        $discount = $this->money($body, 'discount', $errors);
        if ($discount !== null && (float) $discount > 100.0) {
            $errors['discount'] = 'От 0 до 100.';
        }

        return ['name' => $name, 'description' => $description, 'hasDescription' => $hasDescription, 'price' => $price, 'discount' => $discount];
    }

    /**
     * @param array<string, mixed>  $source
     * @param array<string, string> $errors
     */
    private function int(array $source, string $key, int $default, int $min, int $max, array &$errors): int
    {
        if (!isset($source[$key]) || $source[$key] === '') {
            return $default;
        }
        $value = filter_var($source[$key], \FILTER_VALIDATE_INT);
        if ($value === false || $value < $min || $value > $max) {
            $errors[$key] = $max === \PHP_INT_MAX
                ? \sprintf('Целое число не меньше %d.', $min)
                : \sprintf('Целое число от %d до %d.', $min, $max);

            return $default;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $source
     */
    private function string(array $source, string $key): ?string
    {
        $value = $source[$key] ?? null;
        if (!\is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Деньги: неотрицательное число, до 2 знаков после точки. Принимает число или строку («1320.5», «1320,50»).
     *
     * @param array<string, mixed>  $source
     * @param array<string, string> $errors
     *
     * @return numeric-string|null
     */
    private function money(array $source, string $key, array &$errors): ?string
    {
        if (!isset($source[$key]) || $source[$key] === '') {
            return null;
        }
        $raw = $source[$key];
        $value = \is_int($raw) || \is_float($raw) ? (string) $raw : (\is_string($raw) ? str_replace(',', '.', trim($raw)) : '');

        if (preg_match('/^\d{1,10}(\.\d{1,2})?$/', $value) !== 1) {
            $errors[$key] = 'Неотрицательное число, не больше двух знаков после точки.';

            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }
}
