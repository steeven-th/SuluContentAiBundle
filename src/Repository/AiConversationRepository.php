<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use ItechWorld\SuluContentAiBundle\Entity\AiConversation;

/**
 * @extends ServiceEntityRepository<AiConversation>
 */
final class AiConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiConversation::class);
    }

    /**
     * Find the conversation attached to a given Sulu resource in a locale.
     */
    public function findOneByResource(string $resourceType, string $resourceId, string $locale): ?AiConversation
    {
        return $this->findOneBy([
            'resourceType' => $resourceType,
            'resourceId' => $resourceId,
            'locale' => $locale,
        ]);
    }
}
