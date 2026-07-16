<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluContentAiBundle\Ai\ProviderApiClient;
use ItechWorld\SuluContentAiBundle\Entity\AiProviderConfig;
use ItechWorld\SuluContentAiBundle\Repository\AiProviderConfigRepository;
use ItechWorld\SuluContentAiBundle\Security\ApiKeyEncryptor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Live provider info for the provider form (nothing stored): the list of available
 * models (light GET, no tokens) to populate the model selects.
 */
#[Route('/admin/api/content-ai/providers', name: 'iw_sulu_content_ai_provider_status.')]
final class AiProviderStatusController extends AbstractController
{
    public function __construct(
        private readonly AiProviderConfigRepository $repository,
        private readonly ApiKeyEncryptor $encryptor,
        private readonly ProviderApiClient $client,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Activate one provider and deactivate all the others (single active provider).
     */
    #[Route('/{id}/activate', name: 'activate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function activate(int $id): JsonResponse
    {
        $config = $this->repository->find($id);
        if (null === $config) {
            return $this->json(['error' => 'Provider not found.'], Response::HTTP_NOT_FOUND);
        }

        foreach ($this->repository->findBy(['enabled' => true]) as $other) {
            if ($other !== $config) {
                $other->setEnabled(false);
            }
        }
        $config->setEnabled(true);
        $this->entityManager->flush();

        return $this->json(['activated' => $id]);
    }

    #[Route('/{id}/models', name: 'models', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function models(int $id): JsonResponse
    {
        [$config, $apiKey, $error] = $this->resolve($id);
        if (null === $config || null === $apiKey) {
            return $this->json(['valid' => false, 'error' => $error, 'models' => []]);
        }

        try {
            $models = $this->client->listModels($config->getProvider(), $apiKey);
        } catch (\Throwable $e) {
            return $this->json(['valid' => false, 'error' => $e->getMessage(), 'models' => []]);
        }

        return $this->json(['valid' => true, 'models' => $models]);
    }

    /**
     * @return array{0: AiProviderConfig|null, 1: string|null, 2: string|null}
     */
    private function resolve(int $id): array
    {
        $config = $this->repository->find($id);
        if (null === $config) {
            return [null, null, 'Provider not found.'];
        }

        $apiKey = null !== $config->getApiKey() ? $this->encryptor->decrypt($config->getApiKey()) : null;
        if (null === $apiKey || '' === $apiKey) {
            return [null, null, 'No API key configured (save one first).'];
        }

        return [$config, $apiKey, null];
    }
}

