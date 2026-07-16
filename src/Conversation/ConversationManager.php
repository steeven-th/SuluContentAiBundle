<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Conversation;

use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluContentAiBundle\Entity\AiConversation;
use ItechWorld\SuluContentAiBundle\Entity\AiMessage;
use ItechWorld\SuluContentAiBundle\Entity\AiMessageRole;
use ItechWorld\SuluContentAiBundle\Repository\AiConversationRepository;

/**
 * Default Doctrine-backed conversation manager.
 *
 * These are the bundle's own entities, so the classic persist + flush pattern
 * applies (unlike Sulu content, which goes through the ContentManager).
 */
final readonly class ConversationManager implements ConversationManagerInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AiConversationRepository $conversationRepository,
    ) {
    }

    public function findOrCreate(string $resourceType, string $resourceId, string $locale): AiConversation
    {
        $conversation = $this->conversationRepository->findOneByResource($resourceType, $resourceId, $locale);

        if (null === $conversation) {
            $conversation = new AiConversation($resourceType, $resourceId, $locale);
            $this->entityManager->persist($conversation);
            $this->entityManager->flush();
        }

        return $conversation;
    }

    public function appendMessage(AiConversation $conversation, AiMessageRole $role, string $content, ?array $metadata = null): AiMessage
    {
        $message = new AiMessage($role, $content, new \DateTimeImmutable(), $metadata);
        $conversation->addMessage($message);

        // The conversation row is updated in place (single UPDATE of the JSON column).
        $this->entityManager->flush();

        return $message;
    }

    public function clear(AiConversation $conversation): void
    {
        $conversation->clearMessages();
        $this->entityManager->flush();
    }
}
