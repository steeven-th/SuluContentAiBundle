<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Controller;

use ItechWorld\SuluContentAiBundle\Admin\ContentAiAdmin;
use ItechWorld\SuluContentAiBundle\Ai\FieldAssistant;
use ItechWorld\SuluContentAiBundle\Entity\AiExpert;
use ItechWorld\SuluContentAiBundle\Entity\AiPrompt;
use ItechWorld\SuluContentAiBundle\Repository\AiExpertRepository;
use ItechWorld\SuluContentAiBundle\Repository\AiPromptRepository;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Back-office API for the per-field writing assistant (stateless): transform a
 * single field's value under an optional expert, and expose the active experts
 * and predefined prompts for the panel's two selects.
 */
#[Route('/admin/api/content-ai/field', name: 'iw_sulu_content_ai_field.')]
final class AiFieldController extends AbstractController
{
    public function __construct(
        private readonly FieldAssistant $fieldAssistant,
        private readonly AiExpertRepository $expertRepository,
        private readonly AiPromptRepository $promptRepository,
        private readonly SecurityCheckerInterface $securityChecker,
    ) {
    }

    /**
     * Active experts and prompts to populate the panel's selects.
     */
    #[Route('/options', name: 'options', methods: ['GET'])]
    public function options(): JsonResponse
    {
        // Listing options only needs the assistant VIEW permission.
        $this->securityChecker->checkPermission(ContentAiAdmin::SECURITY_CONTEXT, PermissionTypes::VIEW);

        $experts = array_map(
            static fn (AiExpert $expert): array => ['id' => $expert->getId(), 'name' => $expert->getName()],
            $this->expertRepository->findEnabled(),
        );

        $prompts = array_map(
            static fn (AiPrompt $prompt): array => ['id' => $prompt->getId(), 'name' => $prompt->getName(), 'prompt' => $prompt->getPrompt()],
            $this->promptRepository->findEnabled(),
        );

        return $this->json(['experts' => $experts, 'prompts' => $prompts]);
    }

    /**
     * Transform a single field's value according to the instruction (+ expert).
     */
    #[Route('/transform', name: 'transform', methods: ['POST'])]
    public function transform(Request $request): JsonResponse
    {
        // Transforming a field's value is an AI edit: require the assistant EDIT permission.
        $this->securityChecker->checkPermission(ContentAiAdmin::SECURITY_CONTEXT, PermissionTypes::EDIT);

        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent(), true) ?? [];

        $value = (string) ($data['value'] ?? '');
        $instruction = (string) ($data['instruction'] ?? '');
        $locale = (string) ($data['locale'] ?? '');
        $isHtml = (bool) ($data['isHtml'] ?? false);
        $expertId = isset($data['expertId']) && is_numeric($data['expertId']) ? (int) $data['expertId'] : null;

        if ('' === $locale) {
            return $this->json(['error' => 'Missing locale.'], Response::HTTP_BAD_REQUEST);
        }

        if ('' === trim($value) && '' === trim($instruction)) {
            return $this->json(['error' => 'Nothing to transform.'], Response::HTTP_BAD_REQUEST);
        }

        $expertPrompt = null;
        if (null !== $expertId) {
            $expertPrompt = $this->expertRepository->find($expertId)?->getSystemPrompt();
        }

        @set_time_limit(120);

        try {
            $result = $this->fieldAssistant->generate($value, $instruction, $locale, $isHtml, $expertPrompt);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        return $this->json(['result' => $result]);
    }
}
