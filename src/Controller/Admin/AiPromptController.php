<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Controller\Admin;

use ItechWorld\SuluContentAiBundle\Admin\AiSettingsAdmin;
use ItechWorld\SuluContentAiBundle\Entity\AiPrompt;

/**
 * Admin REST CRUD for {@see AiPrompt}.
 */
final class AiPromptController extends AbstractCrudController
{
    protected function getEntityClass(): string
    {
        return AiPrompt::class;
    }

    protected function getResourceKey(): string
    {
        return AiPrompt::RESOURCE_KEY;
    }

    protected function getListKey(): string
    {
        return AiPrompt::LIST_KEY;
    }

    protected function createEntity(): object
    {
        return new AiPrompt();
    }

    protected function applyData(object $entity, array $data): void
    {
        \assert($entity instanceof AiPrompt);

        $entity->setName((string) ($data['name'] ?? ''));
        $entity->setPrompt((string) ($data['prompt'] ?? ''));
        $entity->setEnabled((bool) ($data['enabled'] ?? true));
        $entity->setChanged(new \DateTimeImmutable());
    }

    protected function entityToArray(object $entity): array
    {
        \assert($entity instanceof AiPrompt);

        return $entity->toArray();
    }

    public function getSecurityContext(): string
    {
        return AiSettingsAdmin::PROMPTS_SECURITY_CONTEXT;
    }
}
