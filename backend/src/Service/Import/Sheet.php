<?php

declare(strict_types=1);

namespace App\Service\Import;

/**
 * Содержимое листа: заголовки и непустые строки данных.
 */
final readonly class Sheet
{
    /**
     * @param list<string>                      $headers
     * @param array<int, array<string, string>> $rows    номер строки в файле => [заголовок => значение]
     */
    public function __construct(
        public array $headers,
        public array $rows,
    ) {}
}
