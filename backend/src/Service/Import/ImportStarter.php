<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\ImportJob;
use App\Message\ImportMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\UploadedFileInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Принимает файл и ставит импорт в очередь. Сам импорт выполняет воркер.
 */
final readonly class ImportStarter
{
    public function __construct(
        private ImportUploadValidator $validator,
        private UploadStorage $storage,
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
    ) {
    }

    public function start(?UploadedFileInterface $file): ImportJob
    {
        $this->validator->validate($file);
        \assert($file !== null);

        $job = new ImportJob(
            mb_strimwidth(basename((string) $file->getClientFilename()), 0, 255),
            $this->storage->storeUploaded($file),
        );
        // Сначала задача в БД, потом сообщение: воркер не получит id, которого ещё нет.
        $this->em->persist($job);
        $this->em->flush();

        try {
            $this->bus->dispatch(new ImportMessage($job->getId()));
        } catch (\Throwable $e) {
            $job->fail('Очередь импорта недоступна. Попробуйте позже.');
            $this->em->flush();

            throw $e;
        }

        return $job;
    }
}
