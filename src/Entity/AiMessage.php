<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Entity;

/**
 * A single message inside an {@see AiConversation}.
 *
 * This is a plain value object (not a Doctrine entity): messages are stored as a
 * JSON array on the conversation row, since a chat is always read and written as
 * a whole and never queried message-by-message.
 */
final readonly class AiMessage
{
    /**
     * @param array<string, mixed>|null $metadata Optional metadata (model, token usage…)
     */
    public function __construct(
        public AiMessageRole $role,
        public string $content,
        public \DateTimeImmutable $createdAt,
        public ?array $metadata = null,
    ) {
    }

    public function getRole(): AiMessageRole
    {
        return $this->role;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * @return array{role: string, content: string, createdAt: string, metadata: array<string, mixed>|null}
     */
    public function toArray(): array
    {
        return [
            'role' => $this->role->value,
            'content' => $this->content,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'metadata' => $this->metadata,
        ];
    }

    /**
     * @param array{role: string, content: string, createdAt?: string, metadata?: array<string, mixed>|null} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            AiMessageRole::from($data['role']),
            $data['content'],
            new \DateTimeImmutable($data['createdAt'] ?? 'now'),
            $data['metadata'] ?? null,
        );
    }
}
