<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * База для тестов с БД: свежий контейнер и пустые таблицы перед каждым тестом.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected ContainerInterface $container;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->container = TestContainer::build();
        /** @var EntityManagerInterface $em */
        $em = $this->container->get(EntityManagerInterface::class);
        $this->em = $em;

        $this->em->getConnection()->executeStatement(
            'TRUNCATE products, product_attributes, product_images, import_jobs RESTART IDENTITY CASCADE',
        );
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->close();
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    protected function service(string $id): object
    {
        /** @var T $service */
        $service = $this->container->get($id);

        return $service;
    }

    protected function rows(string $table): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM ' . $table);
    }

    protected static function fixture(string $name): string
    {
        return \dirname(__DIR__) . '/data/' . $name;
    }
}
