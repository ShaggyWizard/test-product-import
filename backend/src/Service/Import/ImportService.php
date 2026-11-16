<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\ImportJob;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Выполняет задачу импорта: читает файл, идёт по строкам, пишет товары, ведёт прогресс и отчёт.
 * Плохая строка попадает в отчёт и не останавливает остальные.
 */
final class ImportService
{
    /** Раз в столько строк сохраняем прогресс и очищаем память Doctrine. */
    private const BATCH = 20;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly XlsxReader $reader,
        private readonly RowMapper $mapper,
        private readonly ProductImporter $importer,
        private readonly UploadStorage $storage,
    ) {
    }

    public function run(string $jobId): ImportJob
    {
        $job = $this->job($jobId);

        try {
            $sheet = $this->reader->read($this->storage->path($job->getFilePath()));

            $missing = $this->mapper->missingColumns($sheet->headers);
            if ($missing !== []) {
                throw new ImportFileException('В файле нет обязательных столбцов: ' . implode(', ', $missing) . '.');
            }
            if ($sheet->rows === []) {
                throw new ImportFileException('В файле нет строк с товарами.');
            }

            $job->start(\count($sheet->rows));
            $this->em->flush();

            $seen = [];
            $processed = 0;

            foreach ($sheet->rows as $rowNumber => $cells) {
                $mapped = $this->mapper->map($cells);
                $code = $mapped->externalCode;

                foreach ($mapped->warnings as $warning) {
                    $job->addWarning($rowNumber, $code, $warning->field, $warning->message);
                }
                foreach ($mapped->errors as $error) {
                    $job->addError($rowNumber, $code, $error->field, $error->message);
                }

                if ($mapped->row !== null) {
                    if (isset($seen[$mapped->row->externalCode])) {
                        $job->addError($rowNumber, $code, 'Внешний код', \sprintf(
                            'Повтор внешнего кода: такой же в строке %d. Записана первая строка.',
                            $seen[$mapped->row->externalCode],
                        ));
                    } else {
                        $seen[$mapped->row->externalCode] = $rowNumber;
                        $this->importer->import($mapped->row) ? $job->countCreated() : $job->countUpdated();
                    }
                }

                $job->advance(++$processed);

                if ($processed % self::BATCH === 0) {
                    $this->em->flush();
                    // Без clear() Doctrine держит в памяти все товары и с каждой строкой flush всё медленнее.
                    $this->em->clear();
                    $job = $this->job($jobId);
                }
            }

            $job->finish();
            $this->em->flush();

            return $job;
        } catch (\Throwable $e) {
            return $this->fail($jobId, $e);
        }
    }

    private function job(string $jobId): ImportJob
    {
        $job = $this->em->find(ImportJob::class, $jobId);
        if ($job === null) {
            throw new \RuntimeException(\sprintf('Задача импорта %s не найдена.', $jobId));
        }

        return $job;
    }

    /**
     * Помечает задачу проваленной. После ошибки БД Doctrine закрывает EntityManager,
     * поэтому статус пишем напрямую через соединение.
     */
    private function fail(string $jobId, \Throwable $e): ImportJob
    {
        $reason = $e instanceof ImportFileException
            ? $e->getMessage()
            : 'Внутренняя ошибка импорта. Товары, записанные до ошибки, сохранены.';

        if (!$e instanceof ImportFileException) {
            error_log(\sprintf('Import %s failed: %s: %s', $jobId, $e::class, $e->getMessage()));
        }

        if ($this->em->isOpen()) {
            $this->em->clear();
            $job = $this->job($jobId);
            $job->fail($reason);
            $this->em->flush();

            return $job;
        }

        $this->em->getConnection()->update('import_jobs', [
            'status' => ImportJob::STATUS_FAILED,
            'failure_reason' => $reason,
            'finished_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], ['id' => $jobId]);

        throw $e;
    }
}
