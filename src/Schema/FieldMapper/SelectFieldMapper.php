<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Schema\FieldMapper;

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata;

/**
 * Maps a single_select to a string enum built from its "values" option.
 */
final class SelectFieldMapper implements FieldMapperInterface
{
    public function supports(FieldMetadata $field): bool
    {
        return 'single_select' === $field->getType();
    }

    public function toSchema(FieldMetadata $field, string $locale): ?array
    {
        $values = $field->findOption('values');
        $enum = [];

        if (null !== $values && OptionMetadata::TYPE_COLLECTION === $values->getType()) {
            $collection = $values->getValue();
            if (\is_array($collection)) {
                foreach ($collection as $option) {
                    $enum[] = (string) $option->getName();
                }
            }
        }

        return [] !== $enum
            ? ['type' => 'string', 'enum' => $enum]
            : ['type' => 'string'];
    }

    public function normalize(FieldMetadata $field, mixed $value): mixed
    {
        return \is_scalar($value) ? (string) $value : null;
    }
}
