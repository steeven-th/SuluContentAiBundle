<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Entity;

/**
 * Role of a message within an AI conversation, mirroring the LLM chat roles.
 */
enum AiMessageRole: string
{
    case System = 'system';
    case User = 'user';
    case Assistant = 'assistant';
}
