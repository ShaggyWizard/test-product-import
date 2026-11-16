<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Ошибка входных данных: превращается в ответ 422 со списком полей.
 */
final class ValidationException extends \RuntimeException
{
    /**
     * @param array<string, string> $errors поле => что не так
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Некорректные данные запроса.');
    }
}
