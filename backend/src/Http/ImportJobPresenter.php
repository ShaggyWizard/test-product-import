<?php

declare(strict_types=1);

namespace App\Http;

use App\Entity\ImportJob;

final class ImportJobPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(ImportJob $job): array
    {
        $total = $job->getTotalRows();

        return [
            'id' => $job->getId(),
            'status' => $job->getStatus(),
            'fileName' => $job->getOriginalFilename(),
            'totalRows' => $total,
            'processedRows' => $job->getProcessedRows(),
            // Пока воркер не прочитал файл, строк ещё не знаем — прогресс 0.
            'progress' => $total > 0 ? (int) floor($job->getProcessedRows() * 100 / $total) : ($job->isFinished() ? 100 : 0),
            'created' => $job->getCreatedCount(),
            'updated' => $job->getUpdatedCount(),
            'errors' => $job->getErrors(),
            'warnings' => $job->getWarnings(),
            'failureReason' => $job->getFailureReason(),
            'createdAt' => $job->getCreatedAt()->format(\DATE_ATOM),
            'startedAt' => $job->getStartedAt()?->format(\DATE_ATOM),
            'finishedAt' => $job->getFinishedAt()?->format(\DATE_ATOM),
        ];
    }
}
