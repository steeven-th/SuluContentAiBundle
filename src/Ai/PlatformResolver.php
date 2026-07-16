<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Ai;

use ItechWorld\SuluContentAiBundle\Repository\AiProviderConfigRepository;
use ItechWorld\SuluContentAiBundle\Security\ApiKeyEncryptor;
use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Bridge\Anthropic\Factory as AnthropicFactory;
use Symfony\AI\Platform\Bridge\Mistral\Factory as MistralFactory;
use Symfony\AI\Platform\Bridge\OpenAi\Factory as OpenAiFactory;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Resolves the active AI platform, model and vision model.
 *
 * Hybrid strategy: if an enabled provider is configured in the admin (its API key
 * decrypts), the platform is built dynamically from it; otherwise it falls back to
 * the statically configured platform (.env / ai.yaml). Resolution is memoized.
 */
final class PlatformResolver
{
    private bool $resolved = false;
    private ?PlatformInterface $platform = null;
    private string $model = '';
    private string $visionModel = '';
    private string $providerName = 'mistral';

    public function __construct(
        private readonly AiProviderConfigRepository $repository,
        private readonly ApiKeyEncryptor $encryptor,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire(service: 'ai.platform.mistral')]
        private readonly PlatformInterface $fallbackPlatform,
        #[Autowire(param: 'itech_world_sulu_content_ai.model')]
        private readonly string $fallbackModel,
        #[Autowire(param: 'itech_world_sulu_content_ai.vision_model')]
        private readonly string $fallbackVisionModel,
    ) {
    }

    public function platform(): PlatformInterface
    {
        $this->resolve();

        \assert(null !== $this->platform);

        return $this->platform;
    }

    public function model(): string
    {
        $this->resolve();

        return $this->model;
    }

    public function visionModel(): string
    {
        $this->resolve();

        return $this->visionModel;
    }

    public function providerName(): string
    {
        $this->resolve();

        return $this->providerName;
    }

    /**
     * @return array{provider: string, source: string, model: string, visionModel: string}
     */
    public function describe(): array
    {
        $this->resolve();
        $config = $this->repository->findActive();

        return [
            'provider' => null !== $config && $this->platform !== $this->fallbackPlatform ? $config->getProvider() : 'mistral (env fallback)',
            'source' => $this->platform === $this->fallbackPlatform ? 'env' : 'database',
            'model' => $this->model,
            'visionModel' => $this->visionModel,
        ];
    }

    private function resolve(): void
    {
        if ($this->resolved) {
            return;
        }
        $this->resolved = true;

        // Default to the statically configured platform (.env / ai.yaml).
        $this->platform = $this->fallbackPlatform;
        $this->model = $this->fallbackModel;
        $this->visionModel = $this->fallbackVisionModel;

        $config = $this->repository->findActive();
        if (null === $config) {
            return;
        }

        $apiKey = $this->encryptor->decrypt((string) $config->getApiKey());
        if (null === $apiKey || '' === $apiKey) {
            $this->logger->warning('AI provider API key could not be decrypted; falling back to env configuration.');

            return;
        }

        try {
            $this->platform = $this->buildPlatform($config->getProvider(), $apiKey);
            $this->providerName = $config->getProvider();
        } catch (\Throwable $e) {
            $this->logger->error('Failed to build AI platform from DB config: '.$e->getMessage());
            $this->platform = $this->fallbackPlatform;

            return;
        }

        if (null !== $config->getModel() && '' !== $config->getModel()) {
            $this->model = $config->getModel();
        }
        if (null !== $config->getVisionModel() && '' !== $config->getVisionModel()) {
            $this->visionModel = $config->getVisionModel();
        }
    }

    private function buildPlatform(string $provider, string $apiKey): PlatformInterface
    {
        return match ($provider) {
            'mistral' => MistralFactory::createPlatform($apiKey, $this->httpClient),
            'openai' => OpenAiFactory::createPlatform($apiKey, $this->httpClient),
            'anthropic' => AnthropicFactory::createPlatform($apiKey, $this->httpClient),
            default => throw new \InvalidArgumentException(\sprintf('Unknown AI provider "%s".', $provider)),
        };
    }
}
