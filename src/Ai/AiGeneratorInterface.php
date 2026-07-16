<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Ai;

use ItechWorld\SuluContentAiBundle\Ai\Exception\AiGenerationException;

/**
 * Central abstraction over the underlying symfony/ai agent.
 *
 * Every LLM call in the bundle MUST go through this interface so no other class
 * depends on symfony/ai directly. This is the guard rail against symfony/ai
 * pre-1.0 breaking changes (in particular the planned Agent API redesign):
 * only the implementation of this interface needs to change when the vendor
 * API moves.
 */
interface AiGeneratorInterface
{
    /**
     * Send a prompt and return the raw text answer.
     *
     * @param string      $prompt       The user prompt
     * @param string|null $systemPrompt Optional system prompt override
     *
     * @return string The generated text
     *
     * @throws AiGenerationException When the call fails or returns no text
     */
    public function generateText(string $prompt, ?string $systemPrompt = null): string;

    /**
     * Continue a conversation given its full ordered message history.
     *
     * @param list<array{role: string, content: string}> $history      Ordered messages (roles: user/assistant/system)
     * @param string|null                                $systemPrompt Optional extra system prompt (the agent may already define one)
     *
     * @return string The assistant's reply
     *
     * @throws AiGenerationException When the call fails or returns no text
     */
    public function chat(array $history, ?string $systemPrompt = null): string;

    /**
     * Continue a conversation and return a structured object matching the schema.
     *
     * @param list<array{role: string, content: string}> $history
     * @param array<string, mixed>                       $jsonSchema
     * @param string|null                                $systemPrompt
     *
     * @return array<string, mixed>
     *
     * @throws AiGenerationException When the call fails or the response is not valid JSON
     */
    public function chatStructured(array $history, array $jsonSchema, ?string $systemPrompt = null): array;

    /**
     * Send a prompt constrained to a JSON schema and return the decoded structure.
     *
     * The model is forced to answer with an object matching $jsonSchema (structured
     * output). This is the mechanism used to fill Sulu blocks (phase 3.3).
     *
     * @param string               $prompt       The user prompt
     * @param array<string, mixed> $jsonSchema   A JSON Schema describing the expected object
     * @param string|null          $systemPrompt Optional system prompt override
     *
     * @return array<string, mixed> The decoded response object
     *
     * @throws AiGenerationException When the call fails or the response is not valid JSON
     */
    public function generateStructured(string $prompt, array $jsonSchema, ?string $systemPrompt = null): array;
}
