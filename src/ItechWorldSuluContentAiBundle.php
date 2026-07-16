<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle;

use ItechWorld\SuluContentAiBundle\Entity\AiExpert;
use ItechWorld\SuluContentAiBundle\Entity\AiPrompt;
use ItechWorld\SuluContentAiBundle\Entity\AiProviderConfig;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Main bundle class for the SuluContentAiBundle.
 *
 * Adds AI generative capabilities to Sulu CMS 3.x content: an in-admin chat
 * assistant that fills page/article blocks (features A), and — later — a
 * Telegram bot to create drafts remotely (feature B). Built on top of the
 * symfony/ai component (multi-provider: OpenAI, Mistral, Anthropic).
 *
 * The extension alias is derived automatically from the class name by
 * AbstractBundle, giving the config prefix "itech_world_sulu_content_ai".
 */
class ItechWorldSuluContentAiBundle extends AbstractBundle
{
    /**
     * Define the bundle configuration schema.
     *
     * Kept intentionally minimal at bootstrap (phase 1.1). The full
     * multi-provider wiring (platforms, models, per-task model mapping)
     * is fleshed out in phase 1.2 on top of symfony/ai-bundle.
     */
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('default_provider')
                    ->defaultValue('openai')
                    ->info('Default AI provider used when a task does not specify one (openai, anthropic, mistral)')
                ->end()
                ->scalarNode('model')
                    ->defaultValue('mistral-small-latest')
                    ->info('Model used for parallel block generation (must accept json_schema response_format)')
                ->end()
                ->scalarNode('vision_model')
                    ->defaultValue('pixtral-12b-latest')
                    ->info('Vision-capable model used to describe images (must accept image input)')
                ->end()
                ->scalarNode('encryption_key')
                    ->defaultValue('%env(APP_SECRET)%')
                    ->info('Secret used to encrypt provider API keys stored in the database (defaults to APP_SECRET)')
                ->end()
            ->end();
    }

    /**
     * Register the Doctrine ORM mapping for this bundle's entities.
     */
    public function prependExtension(
        ContainerConfigurator $container,
        ContainerBuilder $builder,
    ): void {
        // Register the dedicated image format used for AI vision analysis.
        if ($builder->hasExtension('sulu_media')) {
            $builder->prependExtensionConfig('sulu_media', [
                'image_format_files' => [__DIR__.'/../config/image-formats.xml'],
            ]);
        }

        // Register the admin CRUD metadata (lists/forms) and resource → route mapping
        // for the AI experts and prompts.
        if ($builder->hasExtension('sulu_admin')) {
            $builder->prependExtensionConfig('sulu_admin', [
                'lists' => ['directories' => [__DIR__.'/../config/lists']],
                'forms' => ['directories' => [__DIR__.'/../config/forms']],
                'resources' => [
                    AiExpert::RESOURCE_KEY => [
                        'routes' => [
                            'list' => 'iw_sulu_content_ai.get_experts',
                            'detail' => 'iw_sulu_content_ai.get_expert',
                        ],
                    ],
                    AiPrompt::RESOURCE_KEY => [
                        'routes' => [
                            'list' => 'iw_sulu_content_ai.get_prompts',
                            'detail' => 'iw_sulu_content_ai.get_prompt',
                        ],
                    ],
                    AiProviderConfig::RESOURCE_KEY => [
                        'routes' => [
                            'list' => 'iw_sulu_content_ai.get_providers',
                            'detail' => 'iw_sulu_content_ai.get_provider',
                        ],
                    ],
                ],
            ]);
        }

        if (!$builder->hasExtension('doctrine')) {
            return;
        }

        $builder->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'ItechWorldSuluContentAiBundle' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => __DIR__.'/Entity',
                        'prefix' => 'ItechWorld\\SuluContentAiBundle\\Entity',
                        'alias' => 'ItechWorldSuluContentAiBundle',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Load bundle services and expose configuration as container parameters.
     *
     * @param array{default_provider: string, model: string, vision_model: string, encryption_key: string} $config
     */
    public function loadExtension(
        array $config,
        ContainerConfigurator $container,
        ContainerBuilder $builder,
    ): void {
        $container->parameters()->set(
            'itech_world_sulu_content_ai.default_provider',
            $config['default_provider'],
        );
        $container->parameters()->set(
            'itech_world_sulu_content_ai.model',
            $config['model'],
        );
        $container->parameters()->set(
            'itech_world_sulu_content_ai.vision_model',
            $config['vision_model'],
        );
        $container->parameters()->set(
            'itech_world_sulu_content_ai.encryption_key',
            $config['encryption_key'],
        );

        $container->import('../config/services.yaml');
    }
}
