<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Schema;

use ItechWorld\SuluContentAiBundle\Schema\FieldMapper\FieldMapperInterface;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\ItemMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\SectionMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderRegistry;

/**
 * Builds LLM-oriented JSON Schemas from Sulu template metadata (pages, articles,
 * snippets, blocks) and normalizes the model's output back into Sulu's storage
 * format.
 *
 * The structure is derived entirely from XML metadata; each leaf field is handled
 * by a {@see FieldMapperInterface} (extensible per project). Block properties are
 * handled recursively. Fields with no mapper are left untouched.
 */
final class ContentSchemaGenerator
{
    private const RESOURCE_TYPE_MAP = [
        'pages' => 'page',
        'articles' => 'article',
        'snippets' => 'snippet',
    ];

    /**
     * Text field types forced to "required" so the model actually fills them.
     */
    private const PRIMARY_TEXT_TYPES = ['text_line', 'text_area', 'text_editor'];

    /**
     * @var list<FieldMapperInterface>
     */
    private array $mappers;

    /**
     * @param iterable<FieldMapperInterface> $mappers
     */
    public function __construct(
        private readonly MetadataProviderRegistry $metadataProviderRegistry,
        iterable $mappers,
    ) {
        $this->mappers = $mappers instanceof \Traversable ? iterator_to_array($mappers, false) : array_values($mappers);
    }

    /**
     * "Structure" schema of a template: scalar fields filled, block properties
     * reduced to a list of their TYPE only (the blocks are filled one by one in a
     * second step — a big oneOf is unreliable with some models).
     *
     * @return array<string, mixed>
     */
    public function generateStructureSchema(string $resourceType, string $templateKey, string $locale = 'en', bool $contentOnly = true): array
    {
        $form = $this->loadForm($this->metadataType($resourceType), $templateKey, $locale);

        return $this->buildObjectSchema($form->getItems(), $locale, $contentOnly, false, true);
    }

    /**
     * Full JSON Schema of a single block (used to fill it in the second step).
     *
     * @return array<string, mixed>
     */
    public function generateForBlock(string $blockKey, string $locale = 'en', bool $contentOnly = true): array
    {
        $form = $this->loadForm('block', $blockKey, $locale);

        return $this->buildObjectSchema($form->getItems(), $locale, $contentOnly, true, false);
    }

    /**
     * Full template schema (scalars + full block variants). Kept for reference.
     *
     * @return array<string, mixed>
     */
    public function generateForTemplate(string $resourceType, string $templateKey, string $locale = 'en', bool $contentOnly = true): array
    {
        $form = $this->loadForm($this->metadataType($resourceType), $templateKey, $locale);

        return $this->buildObjectSchema($form->getItems(), $locale, $contentOnly, false, false);
    }

    /**
     * Rebuild Sulu storage values from the model's output for a whole template.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function normalizeForTemplate(string $resourceType, string $templateKey, array $data, string $locale = 'en'): array
    {
        $form = $this->loadForm($this->metadataType($resourceType), $templateKey, $locale);

        return $this->normalizeItems($form->getItems(), $data, $locale);
    }

    /**
     * Default values of the fields excluded from the model schema (style pickers,
     * etc.) for a single block, so the rendered block stays valid.
     *
     * @return array<string, mixed>
     */
    public function blockDefaults(string $blockKey, string $locale = 'en'): array
    {
        $form = $this->loadForm('block', $blockKey, $locale);
        $defaults = [];
        $this->collectDefaults($form->getItems(), $defaults);

        return $defaults;
    }

    /**
     * @return list<string>
     */
    public function availableBlockKeys(string $locale = 'en'): array
    {
        return array_keys($this->loadForms('block', $locale));
    }

    /**
     * @return list<string>
     */
    public function availableTemplateKeys(string $resourceType, string $locale = 'en'): array
    {
        return array_keys($this->loadForms($this->metadataType($resourceType), $locale));
    }

    /**
     * @param ItemMetadata[] $items
     *
     * @return array<string, mixed>
     */
    private function buildObjectSchema(array $items, string $locale, bool $contentOnly, bool $requireText, bool $blocksAsTypeOnly): array
    {
        $properties = [];
        $required = [];
        $this->collectSchema($items, $locale, $contentOnly, $requireText, $blocksAsTypeOnly, $properties, $required);

        $schema = [
            'type' => 'object',
            'properties' => $properties,
            'additionalProperties' => false,
        ];

        if ([] !== $required) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * @param ItemMetadata[]       $items
     * @param array<string, mixed> $properties
     * @param list<string>         $required
     */
    private function collectSchema(array $items, string $locale, bool $contentOnly, bool $requireText, bool $blocksAsTypeOnly, array &$properties, array &$required): void
    {
        foreach ($items as $item) {
            if ($item instanceof SectionMetadata) {
                $this->collectSchema($item->getItems(), $locale, $contentOnly, $requireText, $blocksAsTypeOnly, $properties, $required);

                continue;
            }

            if (!$item instanceof FieldMetadata) {
                continue;
            }

            if ('block' === $item->getType()) {
                $properties[$item->getName()] = $this->buildBlockArraySchema($item, $locale, $contentOnly, $blocksAsTypeOnly);

                continue;
            }

            $mapper = $this->mapperFor($item);
            if (null === $mapper) {
                continue; // no mapper → excluded (media, route, unknown custom type…)
            }

            $node = $mapper->toSchema($item, $locale);
            if (null === $node) {
                continue;
            }

            $properties[$item->getName()] = $this->withDescription($node, $item, $locale);

            if ($requireText && \in_array($item->getType(), self::PRIMARY_TEXT_TYPES, true)) {
                $required[] = $item->getName();
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBlockArraySchema(FieldMetadata $blockField, string $locale, bool $contentOnly, bool $blocksAsTypeOnly): array
    {
        $typeKeys = array_map('strval', array_keys($blockField->getTypes()));

        if ($blocksAsTypeOnly) {
            return [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => ['type' => [] === $typeKeys ? ['type' => 'string'] : ['enum' => $typeKeys]],
                    'required' => ['type'],
                    'additionalProperties' => false,
                ],
            ];
        }

        $variants = [];
        foreach ($blockField->getTypes() as $typeKey => $typeForm) {
            $object = $this->buildObjectSchema($typeForm->getItems(), $locale, $contentOnly, true, false);
            $object['properties'] = array_merge(['type' => ['const' => $typeKey]], $object['properties']);
            $object['required'] = array_merge(['type'], $object['required'] ?? []);
            $variants[] = $object;
        }

        return [
            'type' => 'array',
            'items' => [] === $variants ? ['type' => 'object'] : ['oneOf' => $variants],
        ];
    }

    /**
     * @param ItemMetadata[]       $items
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function normalizeItems(array $items, array $data, string $locale): array
    {
        $result = [];

        foreach ($items as $item) {
            if ($item instanceof SectionMetadata) {
                $result = array_merge($result, $this->normalizeItems($item->getItems(), $data, $locale));

                continue;
            }

            if (!$item instanceof FieldMetadata) {
                continue;
            }

            $name = $item->getName();
            if (!\array_key_exists($name, $data)) {
                continue;
            }

            if ('block' === $item->getType()) {
                $result[$name] = $this->normalizeBlocks($item, $data[$name], $locale);

                continue;
            }

            $mapper = $this->mapperFor($item);
            $value = null !== $mapper ? $mapper->normalize($item, $data[$name]) : $data[$name];
            if (null !== $value) {
                $result[$name] = $value;
            }
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeBlocks(FieldMetadata $blockField, mixed $blocksData, string $locale): array
    {
        if (!\is_array($blocksData)) {
            return [];
        }

        /** @var array<string, FormMetadata> $localTypes */
        $localTypes = $blockField->getTypes();
        $result = [];

        foreach ($blocksData as $block) {
            if (!\is_array($block) || !isset($block['type']) || !\is_string($block['type'])) {
                continue;
            }

            $typeKey = $block['type'];
            $items = $this->blockItems($typeKey, $localTypes, $locale);
            if (null === $items) {
                continue;
            }

            $normalized = $this->normalizeItems($items, $block, $locale);
            // Add default values of excluded fields (styles…) so the block renders.
            $defaults = [];
            $this->collectDefaults($items, $defaults);
            $result[] = array_merge($defaults, ['type' => $typeKey], $normalized);
        }

        return $result;
    }

    /**
     * Resolve a block type's fields, preferring the GLOBAL block definition — a
     * template's block "types" are usually just references with no own properties.
     *
     * @param array<string, FormMetadata> $localTypes
     *
     * @return ItemMetadata[]|null
     */
    private function blockItems(string $typeKey, array $localTypes, string $locale): ?array
    {
        try {
            return $this->loadForm('block', $typeKey, $locale)->getItems();
        } catch (\InvalidArgumentException) {
            return isset($localTypes[$typeKey]) ? $localTypes[$typeKey]->getItems() : null;
        }
    }

    /**
     * @param ItemMetadata[]       $items
     * @param array<string, mixed> $defaults
     */
    private function collectDefaults(array $items, array &$defaults): void
    {
        foreach ($items as $item) {
            if ($item instanceof SectionMetadata) {
                $this->collectDefaults($item->getItems(), $defaults);

                continue;
            }

            if (!$item instanceof FieldMetadata || 'block' === $item->getType()) {
                continue;
            }

            $default = $item->findOption('default_value');
            if (null !== $default && null !== $default->getValue() && !\is_array($default->getValue())) {
                $defaults[$item->getName()] = $default->getValue();
            }
        }
    }

    private function mapperFor(FieldMetadata $field): ?FieldMapperInterface
    {
        foreach ($this->mappers as $mapper) {
            if ($mapper->supports($field)) {
                return $mapper;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return array<string, mixed>
     */
    private function withDescription(array $node, FieldMetadata $field, string $locale): array
    {
        $label = $field->getLabel($locale);
        if (null === $label || '' === $label) {
            return $node;
        }

        $hint = str_contains($label, '.') && !str_contains($label, ' ') ? $this->humanize($label) : $label;
        $node['description'] = isset($node['description']) ? $hint.' ('.$node['description'].')' : $hint;

        return $node;
    }

    private function humanize(string $key): string
    {
        $pos = strrpos($key, '.');
        $segment = false !== $pos ? substr($key, $pos + 1) : $key;

        return ucfirst(str_replace('_', ' ', $segment));
    }

    private function loadForm(string $metadataType, string $key, string $locale): FormMetadata
    {
        $forms = $this->loadForms($metadataType, $locale);

        if (!isset($forms[$key])) {
            throw new \InvalidArgumentException(\sprintf(
                'Unknown %s template "%s". Available: %s',
                $metadataType,
                $key,
                implode(', ', array_keys($forms)),
            ));
        }

        return $forms[$key];
    }

    /**
     * @return array<string, FormMetadata>
     */
    private function loadForms(string $metadataType, string $locale): array
    {
        $typed = $this->metadataProviderRegistry->getMetadataProvider('form')->getMetadata($metadataType, $locale);
        \assert($typed instanceof TypedFormMetadata);

        return $typed->getForms();
    }

    private function metadataType(string $resourceType): string
    {
        return self::RESOURCE_TYPE_MAP[$resourceType] ?? rtrim($resourceType, 's');
    }
}
