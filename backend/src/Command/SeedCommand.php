<?php

declare(strict_types=1);

namespace App\Command;

use App\Fixtures\ProductFixture;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:seed', description: 'Заполнить БД тестовыми товарами, атрибутами и картинками')]
final class SeedCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('append', null, InputOption::VALUE_NONE, 'Не очищать таблицы перед загрузкой');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $loader = new Loader();
        $loader->addFixture(new ProductFixture());

        $executor = new ORMExecutor($this->em, new ORMPurger($this->em, ['doctrine_migration_versions']));
        $executor->execute($loader->getFixtures(), append: (bool) $input->getOption('append'));

        $output->writeln(\sprintf('<info>Загружено товаров: %d</info>', ProductFixture::COUNT));

        return Command::SUCCESS;
    }
}
