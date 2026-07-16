<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Controller;

use ItechWorld\SuluContentAiBundle\Ai\AiGeneratorInterface;
use ItechWorld\SuluContentAiBundle\Ai\ContentGenerator;
use ItechWorld\SuluContentAiBundle\Conversation\ConversationManagerInterface;
use ItechWorld\SuluContentAiBundle\Entity\AiConversation;
use ItechWorld\SuluContentAiBundle\Entity\AiMessageRole;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Back-office chat API for the AI assistant.
 *
 * Scopes each conversation to the edited resource (resourceKey + id + locale). When
 * content capabilities are enabled, it delegates content generation to
 * {@see ContentGenerator} and returns the content for the client to apply.
 */
#[Route('/admin/api/content-ai', name: 'iw_sulu_content_ai.')]
final class AiChatController extends AbstractController
{
    public function __construct(
        private readonly ConversationManagerInterface $conversationManager,
        private readonly AiGeneratorInterface $aiGenerator,
        private readonly ContentGenerator $contentGenerator,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Return the stored conversation history for a resource.
     */
    #[Route('/history', name: 'history', methods: ['GET'])]
    public function history(Request $request): JsonResponse
    {
        $resourceKey = (string) $request->query->get('resourceKey', '');
        $id = (string) $request->query->get('id', '');
        $locale = (string) $request->query->get('locale', '');

        if ('' === $resourceKey || '' === $id || '' === $locale) {
            return $this->json(['messages' => []]);
        }

        $conversation = $this->conversationManager->findOrCreate($resourceKey, $id, $locale);

        return $this->json(['messages' => $this->serializeMessages($conversation)]);
    }

    /**
     * Send a new prompt and, when allowed, return generated content to apply.
     */
    #[Route('/chat', name: 'chat', methods: ['POST'])]
    public function chat(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent(), true) ?? [];

        $resourceKey = (string) ($data['resourceKey'] ?? '');
        $id = null !== ($data['id'] ?? null) ? (string) $data['id'] : '';
        $locale = (string) ($data['locale'] ?? '');
        $message = trim((string) ($data['message'] ?? ''));
        $template = (string) ($data['template'] ?? '');
        $canCreate = (bool) ($data['canCreateContent'] ?? false);
        $canModify = (bool) ($data['canModifyContent'] ?? false);
        // Off by default: never overwrite the editor's page title unless allowed.
        $canModifyTitle = (bool) ($data['canModifyTitle'] ?? false);
        /** @var array<string, mixed> $currentContent */
        $currentContent = \is_array($data['currentContent'] ?? null) ? $data['currentContent'] : [];

        if ('' === $message) {
            return $this->json(['error' => 'Empty message.'], Response::HTTP_BAD_REQUEST);
        }

        if ('' === $resourceKey || '' === $id || '' === $locale) {
            return $this->json(
                ['error' => 'The resource must be saved before using the assistant.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $conversation = $this->conversationManager->findOrCreate($resourceKey, $id, $locale);
        $this->conversationManager->appendMessage($conversation, AiMessageRole::User, $message);

        $history = [];
        foreach ($conversation->getMessages() as $entry) {
            $history[] = ['role' => $entry->getRole()->value, 'content' => $entry->getContent()];
        }

        // Content generation (two-step) when a capability is on and a template is known.
        if ($canCreate || $canModify) {
            // The two-step generation makes several sequential LLM calls; raise the
            // execution limit so a page generation is not killed by PHP-FPM (30s).
            @set_time_limit(180);

            try {
                if ($canModify && [] !== $currentContent) {
                    // Transform the existing content in place (translate, rephrase, shorten…).
                    $generated = $this->contentGenerator->transform($resourceKey, $template, $locale, $history, $currentContent, $canModifyTitle);
                    $mode = 'replace';
                } else {
                    // Generate new content, appended to the document.
                    $generated = $this->contentGenerator->generate($resourceKey, $template, $locale, $history, $currentContent, false, $canModifyTitle);
                    $mode = 'append';
                }

                $reply = $generated['reply'];
                $this->conversationManager->appendMessage($conversation, AiMessageRole::Assistant, '' !== $reply ? $reply : '(content generated)');

                return $this->json([
                    'message' => $reply,
                    'content' => $generated['content'],
                    'mode' => $mode,
                ]);
            } catch (\InvalidArgumentException) {
                // Unknown template → fall back to plain chat below.
            } catch (\Throwable $e) {
                return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
            }
        }

        try {
            $answer = $this->aiGenerator->chat($history);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        $this->conversationManager->appendMessage($conversation, AiMessageRole::Assistant, $answer);

        return $this->json(['message' => $answer, 'content' => null, 'mode' => 'append']);
    }

    /**
     * Clear the conversation context for a resource.
     */
    #[Route('/clear', name: 'clear', methods: ['POST'])]
    public function clear(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent(), true) ?? [];

        $resourceKey = (string) ($data['resourceKey'] ?? '');
        $id = null !== ($data['id'] ?? null) ? (string) $data['id'] : '';
        $locale = (string) ($data['locale'] ?? '');

        if ('' === $resourceKey || '' === $id || '' === $locale) {
            return $this->json(['error' => 'Missing resource.'], Response::HTTP_BAD_REQUEST);
        }

        $conversation = $this->conversationManager->findOrCreate($resourceKey, $id, $locale);
        $this->conversationManager->clear($conversation);

        return $this->json(['messages' => []]);
    }

    /**
     * @return list<array{role: string, content: string, createdAt: string}>
     */
    private function serializeMessages(AiConversation $conversation): array
    {
        $messages = [];
        foreach ($conversation->getMessages() as $message) {
            $messages[] = [
                'role' => $message->getRole()->value,
                'content' => $message->getContent(),
                'createdAt' => $message->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ];
        }

        return $messages;
    }
}
