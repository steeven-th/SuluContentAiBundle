<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Controller\Admin;

use ItechWorld\SuluContentAiBundle\Admin\AiSettingsAdmin;
use ItechWorld\SuluContentAiBundle\Entity\AiProviderConfig;
use ItechWorld\SuluContentAiBundle\Repository\AiProviderConfigRepository;
use ItechWorld\SuluContentAiBundle\Security\ApiKeyEncryptor;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Admin REST CRUD for {@see AiProviderConfig}.
 *
 * The API key is encrypted on write and NEVER returned in plaintext: the list/get
 * responses only expose whether a key is set. Submitting an empty key keeps the
 * existing one.
 */
final class AiProviderConfigController extends AbstractCrudController
{
    private ApiKeyEncryptor $encryptor;
    private AiProviderConfigRepository $providerRepository;

    #[Required]
    public function setEncryptor(ApiKeyEncryptor $encryptor): void
    {
        $this->encryptor = $encryptor;
    }

    #[Required]
    public function setProviderRepository(AiProviderConfigRepository $providerRepository): void
    {
        $this->providerRepository = $providerRepository;
    }

    protected function getEntityClass(): string
    {
        return AiProviderConfig::class;
    }

    protected function getResourceKey(): string
    {
        return AiProviderConfig::RESOURCE_KEY;
    }

    protected function getListKey(): string
    {
        return AiProviderConfig::LIST_KEY;
    }

    protected function createEntity(): object
    {
        return new AiProviderConfig();
    }

    protected function applyData(object $entity, array $data): void
    {
        \assert($entity instanceof AiProviderConfig);

        $entity->setProvider((string) ($data['provider'] ?? 'mistral'));
        $entity->setModel($this->nullableString($data['model'] ?? null));
        $entity->setVisionModel($this->nullableString($data['visionModel'] ?? null));

        $enabled = (bool) ($data['enabled'] ?? true);
        $entity->setEnabled($enabled);

        // Only one provider active at a time: disable the others when enabling this one.
        if ($enabled) {
            foreach ($this->providerRepository->findBy(['enabled' => true]) as $other) {
                if ($other !== $entity) {
                    $other->setEnabled(false);
                }
            }
        }

        // Encrypt only when a new non-empty key is submitted; keep existing otherwise.
        $apiKey = $data['apiKey'] ?? null;
        if (\is_string($apiKey) && '' !== trim($apiKey)) {
            $entity->setApiKey($this->encryptor->encrypt(trim($apiKey)));
        }

        $entity->setChanged(new \DateTimeImmutable());
    }

    protected function entityToArray(object $entity): array
    {
        \assert($entity instanceof AiProviderConfig);

        return [
            'id' => $entity->getId(),
            'provider' => $entity->getProvider(),
            // The key is write-only: never expose it, just whether one is set.
            'apiKey' => '',
            'hasApiKey' => null !== $entity->getApiKey() && '' !== $entity->getApiKey(),
            'model' => $entity->getModel(),
            'visionModel' => $entity->getVisionModel(),
            'enabled' => $entity->isEnabled(),
        ];
    }

    public function getSecurityContext(): string
    {
        return AiSettingsAdmin::PROVIDERS_SECURITY_CONTEXT;
    }

    private function nullableString(mixed $value): ?string
    {
        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
