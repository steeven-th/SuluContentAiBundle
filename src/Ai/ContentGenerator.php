<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Ai;

use ItechWorld\SuluContentAiBundle\Schema\ContentSchemaGenerator;

/**
 * Generates a whole template's content in two steps (a single call over a large
 * "oneOf" block schema is unreliable — models pick the block types but leave them
 * empty):
 *
 *   1. Structure: fill scalar fields + choose the SEQUENCE of block types.
 *   2. Fill every block with its own schema — all blocks IN PARALLEL.
 *
 * The result is then normalized into Sulu's storage format (with style defaults).
 */
final readonly class ContentGenerator
{
    /**
     * Safety cap against a runaway block list (not a UX limit).
     */
    private const MAX_BLOCKS = 25;

    public function __construct(
        private AiGeneratorInterface $aiGenerator,
        private PlatformStructuredGenerator $platformGenerator,
        private ContentSchemaGenerator $schemaGenerator,
    ) {
    }

    /**
     * @param list<array{role: string, content: string}> $history
     * @param array<string, mixed>                       $currentContent
     * @param bool                                       $modifyTitle Whether the AI may set the page title (kept out otherwise)
     *
     * @return array{reply: string, content: array<string, mixed>}
     */
    public function generate(string $resourceType, string $template, string $locale, array $history, array $currentContent, bool $canModify, bool $modifyTitle = true): array
    {
        // Step 1 — structure (scalar fields + block type sequence).
        $responseSchema = [
            'type' => 'object',
            'properties' => [
                'reply' => ['type' => 'string', 'description' => 'A short message to the editor, or your answer if no content is generated.'],
                'content' => $this->schemaGenerator->generateStructureSchema($resourceType, $template, $locale),
            ],
            'required' => ['reply', 'content'],
            'additionalProperties' => false,
        ];

        $result = $this->aiGenerator->chatStructured($history, $responseSchema, $this->structurePrompt($locale, $canModify, $currentContent));
        $reply = (string) ($result['reply'] ?? '');
        /** @var array<string, mixed> $content */
        $content = \is_array($result['content'] ?? null) ? $result['content'] : [];

        // Step 2 — fill every block in parallel, then normalize.
        $content = $this->fillBlocks($content, $locale, $this->lastUserMessage($history));
        $content = $this->schemaGenerator->normalizeForTemplate($resourceType, $template, $content, $locale);

        // The page title ("title" is Sulu's convention for pages/articles/snippets)
        // is left untouched unless the editor explicitly allowed it.
        if (!$modifyTitle) {
            unset($content['title']);
        }

        return ['reply' => $reply, 'content' => $content];
    }

    /**
     * Transform EXISTING content in place (translate, rephrase, shorten, correct…)
     * according to the editor's instruction, keeping the structure intact:
     *
     *   1. Plan: the model decides WHICH blocks (and the title) the instruction
     *      targets — the whole page, or just the "intro block", etc.
     *   2. Transform each targeted block's TEXT fields IN PARALLEL, then merge the
     *      new text back (media, styles, block types and untouched blocks are kept).
     *
     * Only text fields (text_line/area/editor) are rewritten, so no re-normalization
     * is needed and links/media/styles are never altered.
     *
     * @param list<array{role: string, content: string}> $history
     * @param array<string, mixed>                       $currentContent
     * @param bool                                       $modifyTitle Whether the AI may transform the page title
     *
     * @return array{reply: string, content: array<string, mixed>}
     */
    public function transform(string $resourceType, string $template, string $locale, array $history, array $currentContent, bool $modifyTitle): array
    {
        $blockField = $this->findBlockField($currentContent);
        /** @var list<array<string, mixed>> $blocks */
        $blocks = null !== $blockField && \is_array($currentContent[$blockField]) ? array_values($currentContent[$blockField]) : [];

        // Step 1 — plan which blocks (and the title) the instruction targets.
        $plan = $this->planTransform($history, $blocks, $locale, $modifyTitle);
        $reply = (string) ($plan['reply'] ?? '');
        $targets = $this->normalizeTargets($plan['blockIndices'] ?? [], \count($blocks));
        $transformTitle = $modifyTitle && (bool) ($plan['transformTitle'] ?? false);

        $instruction = $this->lastUserMessage($history);

        // Step 2 — build one transform request per targeted block (+ title).
        $requests = [];
        $slots = [];

        if ($transformTitle && isset($currentContent['title']) && \is_string($currentContent['title']) && '' !== trim($currentContent['title'])) {
            $requests[] = [
                'prompt' => $this->transformPrompt($instruction, $locale, ['title' => $currentContent['title']]),
                'schema' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string']], 'required' => ['title'], 'additionalProperties' => false],
                'system' => $this->transformSystem($locale),
            ];
            $slots[] = ['title', null];
        }

        foreach ($targets as $index) {
            $block = $blocks[$index];
            if (!isset($block['type']) || !\is_string($block['type'])) {
                continue;
            }

            try {
                $blockSchema = $this->schemaGenerator->generateForBlock($block['type'], $locale);
            } catch (\InvalidArgumentException) {
                continue;
            }

            // Keep only the block's non-empty TEXT fields (skip selects/links/media).
            $currentText = [];
            foreach ($this->textFieldKeys($blockSchema) as $key) {
                if (isset($block[$key]) && \is_string($block[$key]) && '' !== trim($block[$key])) {
                    $currentText[$key] = $block[$key];
                }
            }

            if ([] === $currentText) {
                continue;
            }

            $requests[] = [
                'prompt' => $this->transformPrompt($instruction, $locale, $currentText),
                'schema' => $this->schemaForKeys($blockSchema, array_keys($currentText)),
                'system' => $this->transformSystem($locale),
            ];
            $slots[] = ['block', $index];
        }

        if ([] === $requests) {
            return ['reply' => $reply, 'content' => []];
        }

        $results = $this->platformGenerator->generateBatch($requests);

        // Merge the transformed text back; rebuild ONLY the changed properties so the
        // client can apply them in "replace" mode without touching anything else.
        $content = [];
        $newBlocks = $blocks;
        foreach ($slots as $i => [$kind, $index]) {
            $data = $results[$i] ?? null;
            if (!\is_array($data)) {
                continue;
            }

            if ('title' === $kind) {
                if (isset($data['title']) && \is_string($data['title']) && '' !== trim($data['title'])) {
                    $content['title'] = $data['title'];
                }

                continue;
            }

            if (null !== $index && \is_array($newBlocks[$index] ?? null)) {
                foreach ($data as $key => $value) {
                    if (\is_string($value)) {
                        $newBlocks[$index][$key] = $value;
                    }
                }
            }
        }

        if (null !== $blockField && $newBlocks !== $blocks) {
            $content[$blockField] = $newBlocks;
        }

        return ['reply' => $reply, 'content' => $content];
    }

    /**
     * @param array<string, mixed> $content
     *
     * @return array<string, mixed>
     */
    private function fillBlocks(array $content, string $locale, string $subject): array
    {
        $requests = [];
        $slots = [];

        foreach ($content as $key => $value) {
            if (!\is_array($value) || [] === $value) {
                continue;
            }

            $content[$key] = array_values(\array_slice($value, 0, self::MAX_BLOCKS));

            foreach ($content[$key] as $index => $block) {
                if (!\is_array($block) || !isset($block['type']) || !\is_string($block['type'])) {
                    continue;
                }

                try {
                    $schema = $this->schemaGenerator->generateForBlock($block['type'], $locale);
                } catch (\InvalidArgumentException) {
                    continue;
                }

                $requests[] = [
                    'prompt' => \sprintf('Fill a "%s" content block for a page about: %s. Provide rich, complete content.', $block['type'], $subject),
                    'schema' => $schema,
                    'system' => \sprintf('You write Sulu CMS block content. CRITICAL: write ALL text strictly in the "%s" locale, regardless of the language used in the request. You have no media library access: fill text, leave media empty.', $locale)."\n\n".PromptGuidance::HUMAN_STYLE,
                ];
                $slots[] = [$key, $index];
            }
        }

        if ([] === $requests) {
            return $content;
        }

        $filled = $this->platformGenerator->generateBatch($requests);

        foreach ($slots as $requestIndex => [$key, $index]) {
            $data = $filled[$requestIndex] ?? null;
            if (\is_array($data)) {
                $content[$key][$index] = array_merge($content[$key][$index], $data);
            }
        }

        return $content;
    }

    /**
     * @param list<array{role: string, content: string}> $history
     */
    private function lastUserMessage(array $history): string
    {
        for ($i = \count($history) - 1; $i >= 0; --$i) {
            if ('user' === ($history[$i]['role'] ?? '')) {
                return (string) $history[$i]['content'];
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $currentContent
     */
    private function structurePrompt(string $locale, bool $canModify, array $currentContent): string
    {
        $prompt = \sprintf(
            'You are a content assistant in the Sulu CMS admin. CRITICAL LANGUAGE RULE: every piece of CONTENT you generate — the title and '
            .'ALL text fields — MUST be written in the "%1$s" locale, no matter which language the user writes to you in. The page locale ("%1$s") '
            .'is independent from the language of this conversation. Only the short "reply" message may follow the user\'s language. '
            .'Fill the page "content": set the scalar fields (title, etc.) and choose the SEQUENCE of blocks that best structures the page — each '
            .'block is an object with only its "type" (chosen among the allowed values). Put a short message in "reply". If the user is only chatting '
            .'or asking a question, return an EMPTY "content" object {} and just answer in "reply". The blocks\' content is filled afterwards, so here '
            .'you only pick their types and order. Choose block types freely, including visual blocks, to build a rich, well-structured page.',
            $locale,
        );

        if ($canModify) {
            $prompt .= "\n\n".'You MAY reorganize the existing content: the block sequence you return will REPLACE the existing one, so include the '
                .'blocks you want to keep. Current content (JSON): '
                .(string) json_encode($currentContent, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        }

        $prompt .= "\n\n".PromptGuidance::HUMAN_STYLE;

        return $prompt;
    }

    /**
     * Find the first property holding a list of blocks (objects with a "type").
     *
     * @param array<string, mixed> $content
     */
    private function findBlockField(array $content): ?string
    {
        foreach ($content as $key => $value) {
            if (\is_array($value) && [] !== $value && $this->looksLikeBlockList($value)) {
                return (string) $key;
            }
        }

        return null;
    }

    /**
     * @param array<int|string, mixed> $value
     */
    private function looksLikeBlockList(array $value): bool
    {
        foreach ($value as $item) {
            if (\is_array($item) && isset($item['type']) && \is_string($item['type'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ask the model which blocks (and the title) the instruction targets.
     *
     * @param list<array{role: string, content: string}> $history
     * @param list<array<string, mixed>>                 $blocks
     *
     * @return array<string, mixed>
     */
    private function planTransform(array $history, array $blocks, string $locale, bool $canTitle): array
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'reply' => ['type' => 'string', 'description' => 'A short message to the editor describing what you will transform.'],
                'blockIndices' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Indices of the blocks to transform. Include ALL of them when the instruction targets the whole content.',
                ],
                'transformTitle' => ['type' => 'boolean', 'description' => 'Whether the page title must be transformed too.'],
            ],
            'required' => ['reply', 'blockIndices'],
            'additionalProperties' => false,
        ];

        $result = $this->aiGenerator->chatStructured($history, $schema, $this->planSystem($blocks, $canTitle));

        return \is_array($result) ? $result : [];
    }

    /**
     * @param list<array<string, mixed>> $blocks
     */
    private function planSystem(array $blocks, bool $canTitle): string
    {
        $lines = [];
        foreach ($blocks as $i => $block) {
            $type = \is_string($block['type'] ?? null) ? $block['type'] : 'unknown';
            $lines[] = \sprintf('[%d] type=%s — %s', $i, $type, $this->blockExcerpt($block));
        }
        $list = [] === $lines ? '(no blocks)' : implode("\n", $lines);

        return \sprintf(
            'You help edit EXISTING Sulu CMS page content. The editor gives an instruction (e.g. "translate everything", "shorten the intro", '
            .'"make the second block more formal"). Decide which blocks the instruction targets and return their indices in "blockIndices" — include '
            .'ALL indices when the instruction clearly targets the whole content. %s Put a short message to the editor in "reply". Existing blocks:%s',
            $canTitle ? 'Set "transformTitle" to true only if the title should be transformed.' : 'Never transform the title: "transformTitle" must be false.',
            "\n".$list,
        );
    }

    /**
     * Short plain-text excerpt of a block, for the planning prompt.
     *
     * @param array<string, mixed> $block
     */
    private function blockExcerpt(array $block): string
    {
        $parts = [];
        foreach ($block as $key => $value) {
            if ('type' === $key || !\is_string($value) || '' === trim($value)) {
                continue;
            }
            $parts[] = trim(strip_tags($value));
        }

        $text = preg_replace('/\s+/', ' ', implode(' ', $parts)) ?? '';

        return mb_substr(trim($text), 0, 120);
    }

    /**
     * Keep only valid, in-range, de-duplicated block indices.
     *
     * @return list<int>
     */
    private function normalizeTargets(mixed $indices, int $count): array
    {
        if (!\is_array($indices)) {
            return [];
        }

        $out = [];
        foreach ($indices as $index) {
            $n = \is_int($index) ? $index : (\is_string($index) && ctype_digit($index) ? (int) $index : null);
            if (null !== $n && $n >= 0 && $n < $count) {
                $out[$n] = $n;
            }
        }

        return array_values($out);
    }

    /**
     * Keys of the block's TEXT fields (plain strings, excluding select enums).
     *
     * @param array<string, mixed> $blockSchema
     *
     * @return list<string>
     */
    private function textFieldKeys(array $blockSchema): array
    {
        $keys = [];
        /** @var array<string, mixed> $properties */
        $properties = \is_array($blockSchema['properties'] ?? null) ? $blockSchema['properties'] : [];
        foreach ($properties as $key => $prop) {
            if (\is_array($prop) && 'string' === ($prop['type'] ?? null) && !isset($prop['enum'])) {
                $keys[] = (string) $key;
            }
        }

        return $keys;
    }

    /**
     * A minimal object schema restricted to the given (text) keys.
     *
     * @param array<string, mixed> $blockSchema
     * @param list<string>         $keys
     *
     * @return array<string, mixed>
     */
    private function schemaForKeys(array $blockSchema, array $keys): array
    {
        $properties = [];
        foreach ($keys as $key) {
            if (isset($blockSchema['properties'][$key])) {
                $properties[$key] = $blockSchema['properties'][$key];
            }
        }

        return ['type' => 'object', 'properties' => $properties, 'required' => array_values($keys), 'additionalProperties' => false];
    }

    /**
     * @param array<string, string> $current Current text values to transform
     */
    private function transformPrompt(string $instruction, string $locale, array $current): string
    {
        return \sprintf(
            'Apply this instruction to the values below: "%s". Write the result strictly in the "%s" locale. Keep the meaning and the exact keys; '
            .'rewrite ONLY the text values. When a value contains HTML, keep it as valid HTML. Current values (JSON): %s. Return the transformed values '
            .'for the same keys.',
            $instruction,
            $locale,
            (string) json_encode($current, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES),
        );
    }

    private function transformSystem(string $locale): string
    {
        return \sprintf(
            'You transform EXISTING Sulu CMS content according to the editor\'s instruction (translate, rephrase, shorten, correct…). CRITICAL: output '
            .'all text strictly in the "%s" locale, regardless of the language of the instruction. Preserve HTML structure when present, and never '
            .'invent unrelated content.',
            $locale,
        )."\n\n".PromptGuidance::HUMAN_STYLE;
    }
}
