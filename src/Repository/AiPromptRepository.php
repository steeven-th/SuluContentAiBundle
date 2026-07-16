<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use ItechWorld\SuluContentAiBundle\Entity\AiPrompt;

/**
 * @extends ServiceEntityRepository<AiPrompt>
 */
class AiPromptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiPrompt::class);
    }

    /**
     * @return list<AiPrompt>
     */
    public function findEnabled(): array
    {
        return $this->findBy(['enabled' => true], ['name' => 'ASC']);
    }
}
