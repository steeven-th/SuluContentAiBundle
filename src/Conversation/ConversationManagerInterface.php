<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Conversation;

use ItechWorld\SuluContentAiBundle\Entity\AiConversation;
use ItechWorld\SuluContentAiBundle\Entity\AiMessage;
use ItechWorld\SuluContentAiBundle\Entity\AiMessageRole;

/**
 * Manages persistence of AI chat conversations bound to Sulu resources.
 */
interface ConversationManagerInterface
{
    /**
     * Return the conversation for a resource/locale, creating it if missing.
     */
    public function findOrCreate(string $resourceType, string $resourceId, string $locale): AiConversation;

    /**
     * Persist a new message at the end of the conversation.
     *
     * @param array<string, mixed>|null $metadata Optional metadata (model, token usage…)
     */
    public function appendMessage(AiConversation $conversation, AiMessageRole $role, string $content, ?array $metadata = null): AiMessage;

    /**
     * Reset the conversation context by removing all its messages.
     */
    public function clear(AiConversation $conversation): void;
}
