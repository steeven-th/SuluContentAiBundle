<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use ItechWorld\SuluContentAiBundle\Entity\AiProviderConfig;

/**
 * @extends ServiceEntityRepository<AiProviderConfig>
 */
class AiProviderConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiProviderConfig::class);
    }

    /**
     * The first enabled provider that has an API key, if any (the active one).
     */
    public function findActive(): ?AiProviderConfig
    {
        foreach ($this->findBy(['enabled' => true], ['id' => 'ASC']) as $config) {
            if (null !== $config->getApiKey() && '' !== $config->getApiKey()) {
                return $config;
            }
        }

        return null;
    }
}
