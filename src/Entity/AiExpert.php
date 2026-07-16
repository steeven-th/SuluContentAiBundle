<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use ItechWorld\SuluContentAiBundle\Repository\AiExpertRepository;

/**
 * An "AI expert": a reusable persona (system prompt / brand voice) that the
 * per-field writing assistant can apply. Global (not localized).
 */
#[ORM\Entity(repositoryClass: AiExpertRepository::class)]
#[ORM\Table(name: 'iw_content_ai_expert')]
class AiExpert
{
    public const RESOURCE_KEY = 'iw_ai_experts';
    public const LIST_KEY = 'iw_ai_experts';
    public const FORM_KEY = 'iw_ai_expert_details';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 191)]
    private string $name = '';

    #[ORM\Column(type: 'text')]
    private string $systemPrompt = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

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

    public function getSystemPrompt(): string
    {
        return $this->systemPrompt;
    }

    public function setSystemPrompt(string $systemPrompt): void
    {
        $this->systemPrompt = $systemPrompt;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
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
            'systemPrompt' => $this->systemPrompt,
            'description' => $this->description,
            'enabled' => $this->enabled,
            'created' => $this->created->format(\DateTimeInterface::ATOM),
            'changed' => $this->changed->format(\DateTimeInterface::ATOM),
        ];
    }
}
