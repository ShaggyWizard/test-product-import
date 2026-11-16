<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ImportJob;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<ImportJob>
 */
class ImportJobRepository extends EntityRepository
{
    public function findLatest(): ?ImportJob
    {
        return $this->findOneBy([], ['createdAt' => 'DESC']);
    }
}
