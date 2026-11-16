<?php

declare(strict_types=1);

namespace App\Message;

use App\Entity\ImportJob;
use App\Service\Import\ImportService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * Выполняет импорт в воркере (контейнер worker, команда messenger:consume).
 */
final readonly class ImportMessageHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private ImportService $import,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ImportMessage $message): void
    {
        // Воркер живёт долго: чистим память Doctrine от прошлого сообщения
        // и переподключаемся, если БД успела закрыть простаивающее соединение.
        $this->em->clear();
        $this->reconnectIfNeeded();

        $job = $this->em->find(ImportJob::class, $message->jobId);
        if ($job === null) {
            $this->logger->warning('Import job {id} not found, message skipped', ['id' => $message->jobId]);

            return;
        }
        // Повторная доставка того же сообщения (например, после падения воркера) не запускает импорт дважды.
        if ($job->isFinished()) {
            return;
        }

        try {
            $job = $this->import->run($message->jobId);
        } catch (\Throwable $e) {
            // Задача уже помечена failed в ImportService. Повтор не поможет: ошибка в данных или в БД.
            throw new UnrecoverableMessageHandlingException($e->getMessage(), 0, $e);
        }

        $this->logger->info('Import job {id}: {status}, rows {processed}/{total}, errors {errors}', [
            'id' => $job->getId(),
            'status' => $job->getStatus(),
            'processed' => $job->getProcessedRows(),
            'total' => $job->getTotalRows(),
            'errors' => \count($job->getErrors()),
        ]);
    }

    private function reconnectIfNeeded(): void
    {
        $connection = $this->em->getConnection();
        try {
            $connection->executeQuery($connection->getDatabasePlatform()->getDummySelectSQL());
        } catch (\Throwable) {
            $connection->close();
        }
    }
}
