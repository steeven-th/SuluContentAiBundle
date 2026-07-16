<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use ItechWorld\SuluContentAiBundle\Repository\AiConversationRepository;

/**
 * An AI chat conversation attached to a single Sulu resource in one locale.
 *
 * Uniquely identified by (resourceType, resourceId, locale): one chat history per
 * page/article and per language. Messages are stored inline as a JSON array,
 * since a conversation is always read/written as a whole (see {@see AiMessage}).
 */
#[ORM\Entity(repositoryClass: AiConversationRepository::class)]
#[ORM\Table(name: 'iw_content_ai_conversation')]
#[ORM\UniqueConstraint(
    name: 'uniq_iw_content_ai_conversation_resource',
    columns: ['resource_type', 'resource_id', 'locale'],
)]
class AiConversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'resource_type', type: 'string', length: 32)]
    private string $resourceType;

    #[ORM\Column(name: 'resource_id', type: 'string', length: 64)]
    private string $resourceId;

    #[ORM\Column(type: 'string', length: 10)]
    private string $locale;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /**
     * Ordered messages, stored as a JSON array of {@see AiMessage} snapshots.
     *
     * @var list<array{role: string, content: string, createdAt: string, metadata: array<string, mixed>|null}>
     */
    #[ORM\Column(type: 'json')]
    private array $messages = [];

    public function __construct(string $resourceType, string $resourceId, string $locale)
    {
        $this->resourceType = $resourceType;
        $this->resourceId = $resourceId;
        $this->locale = $locale;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getResourceType(): string
    {
        return $this->resourceType;
    }

    public function getResourceId(): string
    {
        return $this->resourceId;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return list<AiMessage>
     */
    public function getMessages(): array
    {
        return array_map(
            static fn (array $data): AiMessage => AiMessage::fromArray($data),
            $this->messages,
        );
    }

    public function addMessage(AiMessage $message): void
    {
        $this->messages[] = $message->toArray();
        $this->touch();
    }

    /**
     * Reset the conversation context by removing all messages.
     */
    public function clearMessages(): void
    {
        $this->messages = [];
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
