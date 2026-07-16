<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use ItechWorld\SuluContentAiBundle\Repository\AiPromptRepository;

/**
 * A reusable predefined prompt that pre-fills the writing assistant input.
 * Global (not localized).
 */
#[ORM\Entity(repositoryClass: AiPromptRepository::class)]
#[ORM\Table(name: 'iw_content_ai_prompt')]
class AiPrompt
{
    public const RESOURCE_KEY = 'iw_ai_prompts';
    public const LIST_KEY = 'iw_ai_prompts';
    public const FORM_KEY = 'iw_ai_prompt_details';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 191)]
    private string $name = '';

    #[ORM\Column(type: 'text')]
    private string $prompt = '';

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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function setPrompt(string $prompt): void
    {
        $this->prompt = $prompt;
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'prompt' => $this->prompt,
            'enabled' => $this->enabled,
            'created' => $this->created->format(\DateTimeInterface::ATOM),
            'changed' => $this->changed->format(\DateTimeInterface::ATOM),
        ];
    }
}
