<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Сообщение в очередь: «выполни задачу импорта». Несёт только id — файл и статус лежат в import_jobs.
 */
final readonly class ImportMessage
{
    public function __construct(public string $jobId)
    {
    }
}
