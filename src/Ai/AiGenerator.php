<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Ai;

use ItechWorld\SuluContentAiBundle\Ai\Exception\AiGenerationException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * Default AiGenerator built on top of the dynamically-resolved symfony/ai
 * platform (see {@see PlatformResolver}), so the admin-configured provider/key
 * (or the .env fallback) is used everywhere. This class is the single place that
 * talks to symfony/ai, isolating the rest of the bundle from it.
 */
final readonly class AiGenerator implements AiGeneratorInterface
{
    public function __construct(
        private PlatformResolver $resolver,
    ) {
    }

    public function generateText(string $prompt, ?string $systemPrompt = null): string
    {
        $content = $this->call($this->buildMessages($prompt, $systemPrompt))->getContent();

        if (!\is_string($content) || '' === $content) {
            throw new AiGenerationException('The AI returned an empty text response.');
        }

        return $content;
    }

    public function chat(array $history, ?string $systemPrompt = null): string
    {
        $messages = [];

        if (null !== $systemPrompt) {
            $messages[] = Message::forSystem($systemPrompt);
        }

        foreach ($history as $entry) {
            $messages[] = match ($entry['role']) {
                'assistant' => Message::ofAssistant($entry['content']),
                'system' => Message::forSystem($entry['content']),
                default => Message::ofUser($entry['content']),
            };
        }

        $content = $this->call(new MessageBag(...$messages))->getContent();

        if (!\is_string($content) || '' === $content) {
            throw new AiGenerationException('The AI returned an empty text response.');
        }

        return $content;
    }

    public function chatStructured(array $history, array $jsonSchema, ?string $systemPrompt = null): array
    {
        $messages = [];

        if (null !== $systemPrompt) {
            $messages[] = Message::forSystem($systemPrompt);
        }

        foreach ($history as $entry) {
            $messages[] = match ($entry['role']) {
                'assistant' => Message::ofAssistant($entry['content']),
                'system' => Message::forSystem($entry['content']),
                default => Message::ofUser($entry['content']),
            };
        }

        $content = $this->call(
            new MessageBag(...$messages),
            [
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'content_ai_response',
                        // strict:false — a strict oneOf makes the model fill only the
                        // discriminator "type" and skip the block properties.
                        'strict' => false,
                        'schema' => $jsonSchema,
                    ],
                ],
            ],
        )->getContent();

        if (\is_string($content)) {
            $content = json_decode($content, true);

            if (\JSON_ERROR_NONE !== json_last_error()) {
                throw new AiGenerationException('The AI returned invalid JSON: '.json_last_error_msg());
            }
        }

        if (!\is_array($content)) {
            throw new AiGenerationException('The AI did not return a structured object.');
        }

        return $content;
    }

    public function generateStructured(string $prompt, array $jsonSchema, ?string $systemPrompt = null): array
    {
        $content = $this->call(
            $this->buildMessages($prompt, $systemPrompt),
            [
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'content_ai_response',
                        'strict' => true,
                        'schema' => $jsonSchema,
                    ],
                ],
            ],
        )->getContent();

        if (\is_string($content)) {
            $content = json_decode($content, true);

            if (\JSON_ERROR_NONE !== json_last_error()) {
                throw new AiGenerationException('The AI returned invalid JSON: '.json_last_error_msg());
            }
        }

        if (!\is_array($content)) {
            throw new AiGenerationException('The AI did not return a structured object.');
        }

        return $content;
    }

    /**
     * Run the agent call and normalise any vendor error into an AiGenerationException.
     *
     * @param array<string, mixed> $options
     *
     * @throws AiGenerationException
     */
    private function call(MessageBag $messages, array $options = []): ResultInterface
    {
        try {
            return $this->resolver->platform()->invoke($this->resolver->model(), $messages, $options)->getResult();
        } catch (\Throwable $e) {
            throw new AiGenerationException('AI generation failed: '.$e->getMessage(), 0, $e);
        }
    }

    private function buildMessages(string $prompt, ?string $systemPrompt): MessageBag
    {
        if (null !== $systemPrompt) {
            return new MessageBag(
                Message::forSystem($systemPrompt),
                Message::ofUser($prompt),
            );
        }

        return new MessageBag(Message::ofUser($prompt));
    }
}
