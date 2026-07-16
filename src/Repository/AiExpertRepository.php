<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use ItechWorld\SuluContentAiBundle\Entity\AiExpert;

/**
 * @extends ServiceEntityRepository<AiExpert>
 */
class AiExpertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiExpert::class);
    }

    /**
     * @return list<AiExpert>
     */
    public function findEnabled(): array
    {
        return $this->findBy(['enabled' => true], ['name' => 'ASC']);
    }
}
