<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Schema\FieldMapper;

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;

/**
 * Maps text fields (single line, multi-line, rich text) to a plain string.
 */
final class TextFieldMapper implements FieldMapperInterface
{
    private const TYPES = ['text_line', 'text_area', 'text_editor'];

    public function supports(FieldMetadata $field): bool
    {
        return \in_array($field->getType(), self::TYPES, true);
    }

    public function toSchema(FieldMetadata $field, string $locale): ?array
    {
        $node = ['type' => 'string'];

        if ('text_editor' === $field->getType()) {
            $node['description'] = 'rich text / HTML';
        }

        return $node;
    }

    public function normalize(FieldMetadata $field, mixed $value): mixed
    {
        return \is_scalar($value) ? (string) $value : null;
    }
}
