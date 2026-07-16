<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use ItechWorld\SuluContentAiBundle\Repository\AiProviderConfigRepository;

/**
 * Admin-managed configuration of an AI provider: its (encrypted) API key and the
 * models to use. Enables configuring providers from the UI instead of only .env,
 * while the key is stored encrypted at rest.
 */
#[ORM\Entity(repositoryClass: AiProviderConfigRepository::class)]
#[ORM\Table(name: 'iw_content_ai_provider')]
class AiProviderConfig
{
    public const RESOURCE_KEY = 'iw_ai_providers';
    public const LIST_KEY = 'iw_ai_providers';
    public const FORM_KEY = 'iw_ai_provider_details';

    public const PROVIDERS = ['mistral', 'openai', 'anthropic'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 32, unique: true)]
    private string $provider = 'mistral';

    /**
     * Encrypted API key (never stored or returned in plaintext).
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $apiKey = null;

    #[ORM\Column(type: 'string', length: 128, nullable: true)]
    private ?string $model = null;

    #[ORM\Column(type: 'string', length: 128, nullable: true)]
    private ?string $visionModel = null;

    #[ORM\Column(type: 'boolean')]
    private bool $enabled = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $created;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $changed;

    public function __construct()
    {
        $this->created = new \DateTimeImmutable();
        $this->changed = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): void
    {
        $this->provider = $provider;
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function setApiKey(?string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): void
    {
        $this->model = $model;
    }

    public function getVisionModel(): ?string
    {
        return $this->visionModel;
    }

    public function setVisionModel(?string $visionModel): void
    {
        $this->visionModel = $visionModel;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getCreated(): \DateTimeImmutable
    {
        return $this->created;
    }

    public function getChanged(): \DateTimeImmutable
    {
        return $this->changed;
    }

    public function setChanged(\DateTimeImmutable $changed): void
    {
        $this->changed = $changed;
    }
}
