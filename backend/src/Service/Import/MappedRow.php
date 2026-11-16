<?php

declare(strict_types=1);

namespace App\Service\Import;

/**
 * Результат разбора строки: либо готовая строка, либо список ошибок. Предупреждения импорт не останавливают.
 */
final readonly class MappedRow
{
    /**
     * @param list<RowIssue> $errors
     * @param list<RowIssue> $warnings
     */
    public function __construct(
        public ?ParsedRow $row,
        public ?string $externalCode,
        public array $errors,
        public array $warnings,
    ) {
    }
}
