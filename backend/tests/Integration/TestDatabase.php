<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Схема тестовой базы создаётся заново из сущностей один раз на запуск.
 */
final class TestDatabase
{
    public static function recreateSchema(): void
    {
        /** @var EntityManagerInterface $em */
        $em = TestContainer::build()->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
        $em->getConnection()->close();
    }
}
