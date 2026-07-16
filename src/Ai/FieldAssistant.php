<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Ai;

/**
 * Transforms the value of a single form field according to a natural-language
 * instruction, optionally under an AI expert's system prompt (brand voice).
 *
 * Stateless and scoped to one field (used by the per-field writing assistant):
 * no conversation history, no page context.
 */
final readonly class FieldAssistant
{
    public function __construct(
        private AiGeneratorInterface $aiGenerator,
    ) {
    }

    /**
     * @param string      $value       Current field value
     * @param string      $instruction User instruction (e.g. "make it shorter")
     * @param string      $locale      Output locale
     * @param bool        $isHtml      Whether the field holds HTML (text_editor)
     * @param string|null $expertPrompt Optional expert system prompt (brand voice)
     */
    public function generate(string $value, string $instruction, string $locale, bool $isHtml = false, ?string $expertPrompt = null): string
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'result' => ['type' => 'string', 'description' => 'The transformed field value.'],
            ],
            'required' => ['result'],
            'additionalProperties' => false,
        ];

        $system = \sprintf(
            'You are a writing assistant editing a single CMS field. Apply the user\'s instruction to the given value and return ONLY the resulting value. '
            .'CRITICAL: write strictly in the "%s" locale, regardless of the instruction language. %s',
            $locale,
            $isHtml
                ? 'The value is HTML: keep it valid HTML and preserve the existing tag structure where relevant.'
                : 'The value is plain text: return plain text without markup.',
        );

        if (null !== $expertPrompt && '' !== trim($expertPrompt)) {
            $system .= "\n\n".'Follow these expert instructions and brand voice: '.trim($expertPrompt);
        }

        $system .= "\n\n".PromptGuidance::HUMAN_STYLE;

        $prompt = \sprintf(
            "Instruction: %s\n\nCurrent value:\n%s",
            '' !== trim($instruction) ? $instruction : 'Improve this content.',
            $value,
        );

        $result = $this->aiGenerator->generateStructured($prompt, $schema, $system);
        $output = $result['result'] ?? '';

        return \is_string($output) ? trim($output) : '';
    }
}
