<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Schema\FieldMapper;

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;

/**
 * Maps a Sulu form field (a leaf property) to and from an LLM-friendly form.
 *
 * A mapper declares which field types it handles, produces the JSON Schema node
 * exposed to the model (or null to exclude the field entirely), and converts the
 * value returned by the model back into Sulu's storage format.
 *
 * Projects can register their own mappers (services implementing this interface
 * are auto-tagged) to teach the assistant how to fill their custom field types.
 * Fields with no supporting mapper are left untouched (default value).
 */
interface FieldMapperInterface
{
    public function supports(FieldMetadata $field): bool;

    /**
     * The JSON Schema node exposed to the model, or null to exclude the field.
     *
     * @return array<string, mixed>|null
     */
    public function toSchema(FieldMetadata $field, string $locale): ?array;

    /**
     * Convert the model-provided value into Sulu's storage format.
     */
    public function normalize(FieldMetadata $field, mixed $value): mixed;
}
