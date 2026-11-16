<?php

declare(strict_types=1);

namespace App\Service\Import;

/**
 * Файл целиком нельзя импортировать: не читается, нет заголовков или обязательных столбцов.
 * Текст исключения показывается пользователю в отчёте.
 */
final class ImportFileException extends \RuntimeException
{
}
