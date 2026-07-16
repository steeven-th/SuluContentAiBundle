<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Ai;

/**
 * Generates SEO metadata (title, description, keywords) from a page/article's
 * content. SEO fields are standard in Sulu, so a fixed schema is used (no
 * template introspection). Values are written back under /seo/* by the client.
 */
final readonly class SeoGenerator
{
    /**
     * Sulu SEO field limits (see content_seo_metadata.xml: title max 55,
     * description max 320, keywords max 5 comma-separated segments). The
     * description target is kept at the SEO-recommended ~160 chars.
     */
    private const TITLE_MAX = 55;
    private const DESCRIPTION_MAX = 160;
    private const KEYWORDS_MAX = 5;

    /**
     * Root keys that carry no page copy and must not pollute the SEO source text.
     */
    private const SKIP_KEYS = ['type', 'template', 'templateKey', 'url', 'seo', 'settings', 'excerpt', 'author', 'authored', 'changer', 'creator', 'changed', 'created'];

    /**
     * Max characters of source text sent to the model (token budget).
     */
    private const SOURCE_MAX = 4000;

    public function __construct(
        private AiGeneratorInterface $aiGenerator,
    ) {
    }

    /**
     * @param array<string, mixed> $content Full form data (template content at root)
     *
     * @return array{title: string, description: string, keywords: string}
     */
    public function generate(array $content, string $locale): array
    {
        $source = $this->extractText($content);

        $schema = [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string', 'description' => \sprintf('SEO title, compelling and specific, at most %d characters.', self::TITLE_MAX)],
                'description' => ['type' => 'string', 'description' => \sprintf('SEO meta description: one or two COMPLETE sentences, self-contained, aim for ~150 and never exceed %d characters. Must end on a finished sentence — never cut off mid-sentence.', self::DESCRIPTION_MAX)],
                'keywords' => ['type' => 'string', 'description' => \sprintf('Up to %d relevant keywords, comma-separated.', self::KEYWORDS_MAX)],
            ],
            'required' => ['title', 'description', 'keywords'],
            'additionalProperties' => false,
        ];

        $system = \sprintf(
            'You are an SEO expert. Produce optimized, natural (non-spammy) SEO metadata based ONLY on the provided page content. '
            .'CRITICAL LANGUAGE RULE: the source content may be written in a DIFFERENT language, but you MUST write EVERY value strictly in the "%1$s" '
            .'locale (translate as needed) — never mirror the language of the source. Title max %2$d characters. The description must be one or two '
            .'COMPLETE sentences (~150 chars, never over %3$d) that never end mid-sentence. Up to %4$d comma-separated keywords, in the "%1$s" locale.',
            $locale,
            self::TITLE_MAX,
            self::DESCRIPTION_MAX,
            self::KEYWORDS_MAX,
        )."\n\n".PromptGuidance::HUMAN_STYLE;

        $prompt = "Generate SEO metadata for this page content:\n\n".$source;

        $result = $this->aiGenerator->generateStructured($prompt, $schema, $system);

        return [
            'title' => $this->clean((string) ($result['title'] ?? ''), self::TITLE_MAX),
            'description' => $this->cleanSentence((string) ($result['description'] ?? ''), self::DESCRIPTION_MAX),
            'keywords' => $this->cleanKeywords((string) ($result['keywords'] ?? '')),
        ];
    }

    /**
     * Collect the readable page copy (title + block texts) as a single string.
     *
     * @param array<string, mixed> $content
     */
    private function extractText(array $content): string
    {
        $collected = [];
        $this->collectStrings($content, $collected);
        $text = preg_replace('/\s+/', ' ', implode("\n", $collected)) ?? '';

        return mb_substr(trim($text), 0, self::SOURCE_MAX);
    }

    /**
     * @param list<string> $out
     */
    private function collectStrings(mixed $value, array &$out): void
    {
        if (\is_string($value)) {
            $clean = trim(strip_tags($value));
            if ('' !== $clean) {
                $out[] = $clean;
            }

            return;
        }

        if (\is_array($value)) {
            foreach ($value as $key => $item) {
                if (\is_string($key) && \in_array($key, self::SKIP_KEYS, true)) {
                    continue;
                }
                $this->collectStrings($item, $out);
            }
        }
    }

    /**
     * Normalize whitespace, strip tags and cap the length on a word boundary
     * (avoids cutting a word mid-way, which reads badly in SEO tags).
     */
    private function clean(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $truncated = mb_substr($text, 0, $max);
        $lastSpace = mb_strrpos($truncated, ' ');
        if (false !== $lastSpace && $lastSpace > 0) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return rtrim($truncated, " ,.;:—-");
    }

    /**
     * Cap a sentence-like value (the meta description) without leaving it
     * unfinished: cut at the last complete sentence when possible, otherwise on a
     * word boundary, dropping a dangling function word and appending an ellipsis.
     */
    private function cleanSentence(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $slice = mb_substr($text, 0, $max);

        // Prefer the last complete sentence (., !, ?) if it is far enough in.
        if (preg_match('/^(.*[.!?])(?:\s|$)/u', $slice, $matches) && mb_strlen($matches[1]) >= (int) ($max * 0.5)) {
            return trim($matches[1]);
        }

        // Fallback: word boundary, drop trailing punctuation + a dangling short
        // function word (e.g. "à", "de", "and"), then add an ellipsis.
        $lastSpace = mb_strrpos($slice, ' ');
        if (false !== $lastSpace && $lastSpace > 0) {
            $slice = mb_substr($slice, 0, $lastSpace);
        }
        $slice = rtrim($slice, " ,;:—-");
        $slice = preg_replace('/\s+\p{L}{1,3}$/u', '', $slice) ?? $slice;

        return rtrim($slice, " ,;:—-").'…';
    }

    /**
     * Keep at most KEYWORDS_MAX trimmed, non-empty, comma-separated keywords.
     */
    private function cleanKeywords(string $keywords): string
    {
        $parts = array_values(array_filter(
            array_map('trim', explode(',', $keywords)),
            static fn (string $keyword): bool => '' !== $keyword,
        ));

        return implode(', ', \array_slice($parts, 0, self::KEYWORDS_MAX));
    }
}
