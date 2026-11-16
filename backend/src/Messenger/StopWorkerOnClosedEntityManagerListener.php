<?php

declare(strict_types=1);

namespace App\Messenger;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;

/**
 * После ошибки БД Doctrine закрывает EntityManager, и следующие сообщения обработать уже нельзя.
 * Тогда останавливаем воркер — Docker перезапустит контейнер (restart: unless-stopped) с чистым состоянием.
 */
final readonly class StopWorkerOnClosedEntityManagerListener implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if (!$this->em->isOpen()) {
            $this->logger->error('EntityManager is closed, stopping worker to restart it');
            $event->getWorker()->stop();
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [WorkerRunningEvent::class => 'onWorkerRunning'];
    }
}
