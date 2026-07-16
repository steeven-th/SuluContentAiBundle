<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Schema\FieldMapper;

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;

/**
 * Maps a Sulu "link" field (e.g. a button) to a simple {title, url} object that
 * the model can fill, and rebuilds Sulu's external-link storage format from it.
 *
 * The model has no access to internal pages, so links are always external.
 */
final class LinkFieldMapper implements FieldMapperInterface
{
    public function supports(FieldMetadata $field): bool
    {
        return 'link' === $field->getType();
    }

    public function toSchema(FieldMetadata $field, string $locale): ?array
    {
        return [
            'type' => 'object',
            'description' => 'a link/button (external URL)',
            'properties' => [
                'title' => ['type' => 'string', 'description' => 'button label'],
                'url' => ['type' => 'string', 'description' => 'external URL, e.g. https://…'],
            ],
            'required' => ['title', 'url'],
            'additionalProperties' => false,
        ];
    }

    public function normalize(FieldMetadata $field, mixed $value): mixed
    {
        if (!\is_array($value) || empty($value['url'])) {
            return null;
        }

        return [
            'provider' => 'external',
            'href' => (string) $value['url'],
            'target' => '_self',
            'title' => isset($value['title']) ? (string) $value['title'] : null,
            'rel' => null,
        ];
    }
}
