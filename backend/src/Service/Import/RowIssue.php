<?php

declare(strict_types=1);

namespace App\Service\Import;

/**
 * Замечание к строке файла: в каком столбце и что не так.
 */
final readonly class RowIssue
{
    public function __construct(
        public ?string $field,
        public string $message,
    ) {}
}
