<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Controller\Admin;

use ItechWorld\SuluContentAiBundle\Admin\AiSettingsAdmin;
use ItechWorld\SuluContentAiBundle\Entity\AiExpert;

/**
 * Admin REST CRUD for {@see AiExpert}.
 */
final class AiExpertController extends AbstractCrudController
{
    protected function getEntityClass(): string
    {
        return AiExpert::class;
    }

    protected function getResourceKey(): string
    {
        return AiExpert::RESOURCE_KEY;
    }

    protected function getListKey(): string
    {
        return AiExpert::LIST_KEY;
    }

    protected function createEntity(): object
    {
        return new AiExpert();
    }

    protected function applyData(object $entity, array $data): void
    {
        \assert($entity instanceof AiExpert);

        $entity->setName((string) ($data['name'] ?? ''));
        $entity->setSystemPrompt((string) ($data['systemPrompt'] ?? ''));
        $description = $data['description'] ?? null;
        $entity->setDescription(\is_string($description) && '' !== $description ? $description : null);
        $entity->setEnabled((bool) ($data['enabled'] ?? true));
        $entity->setChanged(new \DateTimeImmutable());
    }

    protected function entityToArray(object $entity): array
    {
        \assert($entity instanceof AiExpert);

        return $entity->toArray();
    }

    public function getSecurityContext(): string
    {
        return AiSettingsAdmin::EXPERTS_SECURITY_CONTEXT;
    }
}
