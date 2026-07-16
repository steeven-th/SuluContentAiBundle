<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Ai;

/**
 * Translates a flat set of text fields into a target locale, keeping the keys.
 *
 * Generic on purpose (used for media metadata, and reusable elsewhere): it builds
 * a structured-output schema from the given keys so the model returns each field
 * translated. Empty fields are ignored; untranslated keys fall back to the source.
 */
final readonly class FieldTranslator
{
    public function __construct(
        private AiGeneratorInterface $aiGenerator,
    ) {
    }

    /**
     * @param array<string, string> $fields Key => source text
     *
     * @return array<string, string> Key => translated text (only non-empty inputs)
     */
    public function translate(array $fields, string $targetLocale): array
    {
        $source = [];
        foreach ($fields as $key => $value) {
            if (\is_string($value) && '' !== trim($value)) {
                $source[$key] = $value;
            }
        }

        if ([] === $source) {
            return [];
        }

        $properties = [];
        foreach (array_keys($source) as $key) {
            $properties[$key] = ['type' => 'string'];
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];

        $system = \sprintf(
            'You are a professional translator. Translate every provided value into the "%1$s" locale, preserving meaning, tone and any HTML markup. '
            .'Output strictly in the "%1$s" locale — never keep the source language.',
            $targetLocale,
        );

        $prompt = \sprintf(
            'Translate these values into the "%s" locale, keeping the exact same keys:%s%s',
            $targetLocale,
            "\n\n",
            (string) json_encode($source, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES),
        );

        $result = $this->aiGenerator->generateStructured($prompt, $schema, $system);

        $translated = [];
        foreach ($source as $key => $value) {
            $translated[$key] = isset($result[$key]) && \is_string($result[$key]) && '' !== trim($result[$key])
                ? $result[$key]
                : $value;
        }

        return $translated;
    }
}
