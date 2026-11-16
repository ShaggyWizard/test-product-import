<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ImportJob;
use App\Service\Import\ImportService;
use App\Service\Import\UploadStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:import', description: 'Импортировать товары из .xlsx сразу, без очереди')]
final class ImportCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UploadStorage $storage,
        private readonly ImportService $import,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'Путь к .xlsx внутри контейнера');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $file */
        $file = $input->getArgument('file');

        if (!is_file($file)) {
            $io->error('Файл не найден: ' . $file);

            return Command::FAILURE;
        }

        $job = new ImportJob(basename($file), $this->storage->store($file));
        $this->em->persist($job);
        $this->em->flush();

        $job = $this->import->run($job->getId());

        $io->definitionList(
            ['Задача' => $job->getId()],
            ['Статус' => $job->getStatus()],
            ['Строк' => $job->getProcessedRows() . ' из ' . $job->getTotalRows()],
            ['Создано' => $job->getCreatedCount()],
            ['Обновлено' => $job->getUpdatedCount()],
            ['Ошибок' => \count($job->getErrors())],
            ['Предупреждений' => \count($job->getWarnings())],
        );

        foreach (['Ошибки' => $job->getErrors(), 'Предупреждения' => $job->getWarnings()] as $title => $issues) {
            if ($issues !== []) {
                $io->section($title);
                $io->table(
                    ['Строка', 'Внешний код', 'Столбец', 'Что не так'],
                    array_map(static fn (array $i): array => [$i['row'], $i['externalCode'] ?? '', $i['field'] ?? '', $i['message']], $issues),
                );
            }
        }

        if ($job->getStatus() === ImportJob::STATUS_FAILED) {
            $io->error((string) $job->getFailureReason());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
