<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Ai;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Talks directly to a provider's HTTP API for live information that symfony/ai
 * does not surface: the list of available models (to populate the model selects).
 */
final readonly class ProviderApiClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * List the model ids available for the given provider + key.
     *
     * @return list<string>
     */
    public function listModels(string $provider, string $apiKey): array
    {
        $response = $this->httpClient->request('GET', $this->baseUrl($provider).'/models', [
            'headers' => $this->authHeaders($provider, $apiKey),
            'timeout' => 15,
        ]);

        /** @var array{data?: array<int, array{id?: string}>} $data */
        $data = $response->toArray();

        $models = [];
        foreach ($data['data'] ?? [] as $model) {
            if (isset($model['id']) && \is_string($model['id'])) {
                $models[] = $model['id'];
            }
        }
        sort($models);

        return array_values(array_unique($models));
    }

    private function baseUrl(string $provider): string
    {
        return match ($provider) {
            'mistral' => 'https://api.mistral.ai/v1',
            'openai' => 'https://api.openai.com/v1',
            'anthropic' => 'https://api.anthropic.com/v1',
            default => throw new \InvalidArgumentException(\sprintf('Unknown AI provider "%s".', $provider)),
        };
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(string $provider, string $apiKey): array
    {
        return match ($provider) {
            'anthropic' => ['x-api-key' => $apiKey, 'anthropic-version' => '2023-06-01'],
            default => ['Authorization' => 'Bearer '.$apiKey],
        };
    }
}
