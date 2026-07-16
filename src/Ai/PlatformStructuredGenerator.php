<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Ai;

use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\DeferredResult;

/**
 * Runs several structured-output generations IN PARALLEL via the symfony/ai
 * Platform.
 *
 * Unlike the Agent (which resolves each call synchronously), Platform::invoke()
 * is lazy: launching several invoke() calls without consuming them lets Symfony
 * HttpClient multiplex the requests (curl_multi). The first getResult() drives
 * all of them, so total time ≈ the slowest call, not the sum.
 *
 * A raw json_schema array is passed as response_format so the model returns JSON
 * that we decode ourselves (dynamic per-block schemas — no PHP DTO classes).
 *
 * The platform and model come from {@see PlatformResolver} (admin-configured
 * provider or .env fallback).
 */
final readonly class PlatformStructuredGenerator
{
    public function __construct(
        private PlatformResolver $resolver,
    ) {
    }

    /**
     * Fill several structured requests in parallel; results keep the input order.
     *
     * @param list<array{prompt: string, schema: array<string, mixed>, system?: string}> $requests
     *
     * @return list<array<string, mixed>|null> Decoded objects (null on failure per request)
     */
    public function generateBatch(array $requests): array
    {
        $platform = $this->resolver->platform();
        $model = $this->resolver->model();

        // Phase 1 — launch every call (lazy → parallel HTTP), consume nothing yet.
        /** @var list<DeferredResult> $deferred */
        $deferred = [];
        foreach ($requests as $request) {
            $messages = isset($request['system'])
                ? new MessageBag(Message::forSystem($request['system']), Message::ofUser($request['prompt']))
                : new MessageBag(Message::ofUser($request['prompt']));

            $deferred[] = $platform->invoke($model, $messages, [
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'content_ai_block',
                        'strict' => false,
                        'schema' => $request['schema'],
                    ],
                ],
            ]);
        }

        // Phase 2 — collect (the first getResult() progresses all in-flight requests).
        $results = [];
        foreach ($deferred as $result) {
            $results[] = $this->decode($result);
        }

        return $results;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(DeferredResult $deferred): ?array
    {
        try {
            $content = $deferred->getResult()->getContent();
        } catch (\Throwable) {
            return null;
        }

        if (\is_string($content)) {
            $decoded = json_decode($content, true);

            return \is_array($decoded) ? $decoded : null;
        }

        if (\is_array($content)) {
            return $content;
        }

        return \is_object($content) ? (array) $content : null;
    }
}
