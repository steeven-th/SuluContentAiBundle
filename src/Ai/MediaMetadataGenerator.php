<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Ai;

use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

/**
 * Generates media metadata (title, description/alt-text) from the image itself,
 * using a vision-capable model (Pixtral by default). Optionally improves the
 * current metadata instead of describing from scratch.
 *
 * The platform and vision model come from {@see PlatformResolver} (admin-configured
 * provider or .env fallback).
 */
final readonly class MediaMetadataGenerator
{
    public function __construct(
        private PlatformResolver $resolver,
    ) {
    }

    /**
     * @param array<string, string> $currentMeta Existing metadata to improve (when optimizing)
     *
     * @return array{title: string, description: string}
     */
    public function generate(string $binary, string $mimeType, string $locale, array $currentMeta = []): array
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string', 'description' => 'A short, descriptive title for the image.'],
                'description' => ['type' => 'string', 'description' => 'A descriptive alt-text of the image for accessibility and SEO.'],
            ],
            'required' => ['title', 'description'],
            'additionalProperties' => false,
        ];

        $instruction = 'Generate a concise, descriptive title and an alt-text for this image, suitable for accessibility and SEO.';
        $currentMeta = array_filter($currentMeta, static fn (string $value): bool => '' !== trim($value));
        if ([] !== $currentMeta) {
            $instruction .= ' Improve upon the existing metadata where relevant, staying faithful to the image. Current metadata (JSON): '
                .(string) json_encode($currentMeta, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        }

        $messages = new MessageBag(
            Message::forSystem(\sprintf(
                'You describe images for accessibility and SEO. Output strictly in the "%s" locale, based only on what is actually visible in the image.',
                $locale,
            )."\n\n".PromptGuidance::HUMAN_STYLE),
            Message::ofUser(new Text($instruction), new Image($binary, $mimeType)),
        );

        $result = $this->resolver->platform()->invoke($this->resolver->visionModel(), $messages, [
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => ['name' => 'media_metadata', 'strict' => false, 'schema' => $schema],
            ],
        ])->getResult();

        $content = $result->getContent();
        $decoded = \is_string($content) ? json_decode($content, true) : (\is_array($content) ? $content : null);
        $decoded = \is_array($decoded) ? $decoded : [];

        return [
            'title' => trim((string) ($decoded['title'] ?? '')),
            'description' => trim((string) ($decoded['description'] ?? '')),
        ];
    }
}
