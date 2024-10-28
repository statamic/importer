<?php

namespace Statamic\Importer\Transformers;

use Statamic\Facades\AssetContainer;
use Statamic\Fields\Field;
use Statamic\Fieldtypes\Bard\Augmentor as BardAugmentor;
use Statamic\Importer\WordPress\Gutenberg;
use Statamic\Support\Str;

class BardTransformer extends AbstractTransformer
{
    public function transform(string $value): array
    {
        $this->enableBardButtons();

        if ($this->isGutenbergValue($value)) {
            return Gutenberg::toBard(
                config: $this->config,
                blueprint: $this->blueprint,
                field: $this->field,
                value: $value
            );
        }

        $value = (new BardAugmentor($this->field->fieldtype()))->renderHtmlToProsemirror($value)['content'];

        return collect($value)
            ->map(function (array $node): ?array {
                if ($node['type'] === 'image' && $this->field->get('container') && isset($this->config['assets_base_url'])) {
                    $assetContainer = AssetContainer::find($this->field->get('container'));

                    $transformer = new AssetsTransformer(
                        field: new Field('image', ['container' => $assetContainer->handle(), 'max_files' => 1]),
                        config: [
                            'related_field' => 'url',
                            'base_url' => $this->config['assets_base_url'] ?? null,
                            'download_when_missing' => $this->config['assets_download_when_missing'] ?? false,
                        ]
                    );

                    $asset = $assetContainer->asset(path: $transformer->transform($node['attrs']['src']));

                    if (! $asset) {
                        return null;
                    }

                    $node['attrs']['src'] = $asset->id();
                }

                return $node;
            })
            ->filter()
            ->all();
    }

    private function enableBardButtons(): void
    {
        $this->blueprint->ensureFieldHasConfig(
            handle: $this->field->handle(),
            config: array_merge($this->field->config(), [
                'container' => $this->field->get('container') ?? AssetContainer::all()->first()?->handle(),
                'buttons' => [
                    'h1',
                    'h2',
                    'h3',
                    'bold',
                    'italic',
                    'unorderedlist',
                    'orderedlist',
                    'removeformat',
                    'quote',
                    'anchor',
                    'image',
                    'table',
                    'horizontalrule',
                    'codeblock',
                    'underline',
                    'superscript',
                ],
            ])
        );

        $this->blueprint->save();
    }

    private function isGutenbergValue(string $value): bool
    {
        return Str::contains($value, '<!-- wp:');
    }

    public function fieldItems(): array
    {
        if ($this->field->get('container')) {
            return [
                'assets_base_url' => [
                    'type' => 'text',
                    'display' => __('Assets Base URL'),
                    'instructions' => __('The base URL to prepend to the path.'),
                ],
                'assets_download_when_missing' => [
                    'type' => 'toggle',
                    'display' => __('Download assets when missing?'),
                    'instructions' => __("If the asset can't be found in the asset container, should it be downloaded?"),
                ],
            ];
        }

        return [];
    }
}
