<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Schema\FieldMapper;

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;

/**
 * Maps number and checkbox fields to their scalar JSON types.
 */
final class NumberFieldMapper implements FieldMapperInterface
{
    public function supports(FieldMetadata $field): bool
    {
        return \in_array($field->getType(), ['number', 'checkbox'], true);
    }

    public function toSchema(FieldMetadata $field, string $locale): ?array
    {
        return 'checkbox' === $field->getType()
            ? ['type' => 'boolean']
            : ['type' => 'number'];
    }

    public function normalize(FieldMetadata $field, mixed $value): mixed
    {
        if ('checkbox' === $field->getType()) {
            return (bool) $value;
        }

        return \is_numeric($value) ? 0 + $value : null;
    }
}
