<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Controller;

use ItechWorld\SuluContentAiBundle\Ai\FieldTranslator;
use ItechWorld\SuluContentAiBundle\Ai\MediaMetadataGenerator;
use Sulu\Bundle\MediaBundle\Media\ImageConverter\ImageConverterInterface;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Bundle\MediaBundle\Media\Storage\StorageInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Back-office API for AI operations on media metadata.
 *
 * Translation reads the source-locale metadata server-side (via the media
 * manager) and returns the fields translated into the target locale, for the
 * client to apply on the current form (source → current-locale flow).
 */
#[Route('/admin/api/content-ai/media', name: 'iw_sulu_content_ai_media.')]
final class AiMediaController extends AbstractController
{
    /**
     * Internal image format sent to the vision model (downscaled to save tokens).
     */
    private const AI_FORMAT = 'iw_ai_analysis';

    public function __construct(
        private readonly FieldTranslator $fieldTranslator,
        private readonly MediaMetadataGenerator $mediaMetadataGenerator,
        private readonly MediaManagerInterface $mediaManager,
        private readonly StorageInterface $storage,
        #[Autowire(service: 'sulu_media.image.converter')]
        private readonly ImageConverterInterface $imageConverter,
    ) {
    }

    /**
     * Translate a media's metadata from a source locale into a target locale.
     */
    #[Route('/translate-metadata', name: 'translate', methods: ['POST'])]
    public function translateMetadata(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent(), true) ?? [];

        $id = (int) ($data['id'] ?? 0);
        $sourceLocale = (string) ($data['sourceLocale'] ?? '');
        $targetLocale = (string) ($data['targetLocale'] ?? '');

        if ($id <= 0 || '' === $sourceLocale || '' === $targetLocale) {
            return $this->json(['error' => 'Missing id, sourceLocale or targetLocale.'], Response::HTTP_BAD_REQUEST);
        }

        if ($sourceLocale === $targetLocale) {
            return $this->json(['error' => 'Source and target locales are identical.'], Response::HTTP_BAD_REQUEST);
        }

        @set_time_limit(120);

        try {
            $media = $this->mediaManager->getById($id, $sourceLocale);

            $fields = [
                'title' => (string) ($media->getTitle() ?? ''),
                'description' => (string) ($media->getDescription() ?? ''),
                'copyright' => (string) ($media->getCopyright() ?? ''),
                'credits' => (string) ($media->getCredits() ?? ''),
            ];

            $translated = $this->fieldTranslator->translate($fields, $targetLocale);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        if ([] === $translated) {
            return $this->json(['error' => 'The source locale has no metadata to translate.'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['metadata' => $translated]);
    }

    /**
     * Generate media metadata (title, description) from the image itself, via a
     * vision model. When "optimize" is set, the current metadata is improved.
     */
    #[Route('/generate-metadata', name: 'generate', methods: ['POST'])]
    public function generateMetadata(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent(), true) ?? [];

        $id = (int) ($data['id'] ?? 0);
        $locale = (string) ($data['locale'] ?? '');
        $optimize = (bool) ($data['optimize'] ?? false);

        if ($id <= 0 || '' === $locale) {
            return $this->json(['error' => 'Missing id or locale.'], Response::HTTP_BAD_REQUEST);
        }

        @set_time_limit(120);

        try {
            $media = $this->mediaManager->getById($id, $locale);
            $fileVersion = $media->getFileVersion();
            $mimeType = (string) $fileVersion->getMimeType();

            if (!str_starts_with($mimeType, 'image/')) {
                return $this->json(['error' => 'This media is not an image.'], Response::HTTP_BAD_REQUEST);
            }

            // Send a downscaled thumbnail to the vision model to save tokens;
            // fall back to the original binary if the conversion fails.
            try {
                $binary = (string) $this->imageConverter->convert($fileVersion, self::AI_FORMAT, 'jpg');
                $visionMimeType = 'image/jpeg';
            } catch (\Throwable) {
                $stream = $this->storage->load($fileVersion->getStorageOptions());
                $binary = \is_resource($stream) ? (string) stream_get_contents($stream) : (string) $stream;
                if (\is_resource($stream)) {
                    fclose($stream);
                }
                $visionMimeType = $mimeType;
            }

            $currentMeta = $optimize ? [
                'title' => (string) ($media->getTitle() ?? ''),
                'description' => (string) ($media->getDescription() ?? ''),
            ] : [];

            $metadata = $this->mediaMetadataGenerator->generate($binary, $visionMimeType, $locale, $currentMeta);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        return $this->json(['metadata' => $metadata]);
    }
}

