<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Ai;

/**
 * Shared prompt guidance appended to content-generation system prompts so the
 * output does not read like typical AI-generated text.
 */
final class PromptGuidance
{
    /**
     * Style instruction avoiding the usual "AI tells" (em dashes, emojis, clichés…).
     */
    public const HUMAN_STYLE =
        'Write in a natural, human style and avoid typical AI-generated tells: '
        .'do NOT use em dashes or en dashes ("—", "–"), use commas, parentheses or periods instead. '
        .'Do NOT use emojis. Avoid clichéd AI phrasings (such as "delve into", "in today\'s fast-paced world", '
        .'"it is important to note", "in conclusion", "unlock", "elevate", "game-changer"). '
        .'Do not overuse bullet lists or bold text, and vary sentence length so the text reads as written by a person.';
}
