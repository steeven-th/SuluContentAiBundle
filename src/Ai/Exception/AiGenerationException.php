<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Ai\Exception;

/**
 * Thrown when an AI generation call fails.
 *
 * Covers transport errors, timeouts, empty results, and responses that do not
 * match the requested JSON schema. Callers should catch this single exception
 * type rather than any symfony/ai internal exception.
 */
final class AiGenerationException extends \RuntimeException
{
}
