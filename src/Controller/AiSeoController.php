<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Controller;

use ItechWorld\SuluContentAiBundle\Ai\SeoGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Back-office API generating SEO metadata for the edited page/article.
 *
 * The client sends the current form data (page content lives at the root of the
 * shared form store, even on the SEO tab) and the locale; the response holds the
 * SEO fields to apply under /seo/*.
 */
#[Route('/admin/api/content-ai', name: 'iw_sulu_content_ai_seo.')]
final class AiSeoController extends AbstractController
{
    public function __construct(
        private readonly SeoGenerator $seoGenerator,
    ) {
    }

    /**
     * Generate SEO title/description/keywords from the page content.
     */
    #[Route('/seo', name: 'seo', methods: ['POST'])]
    public function generate(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent(), true) ?? [];

        $locale = (string) ($data['locale'] ?? '');
        /** @var array<string, mixed> $content */
        $content = \is_array($data['content'] ?? null) ? $data['content'] : [];

        if ('' === $locale) {
            return $this->json(['error' => 'Missing locale.'], Response::HTTP_BAD_REQUEST);
        }

        if ([] === $content) {
            return $this->json(['error' => 'The page must have content before generating SEO.'], Response::HTTP_BAD_REQUEST);
        }

        // A single structured LLM call; raise the limit to stay clear of PHP-FPM's default.
        @set_time_limit(120);

        try {
            $seo = $this->seoGenerator->generate($content, $locale);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        return $this->json(['seo' => $seo]);
    }
}
