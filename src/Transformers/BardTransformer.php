<?php

namespace Statamic\Importer\Transformers;

use Facades\Statamic\Importer\Support\FieldUpdater;
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
                if ($node['type'] === 'text') {
                    return [
                        'type' => 'paragraph',
                        'content' => [$node],
                    ];
                }

                if ($node['type'] === 'image' && $this->field->get('container') && isset($this->config['assets_base_url'])) {
                    $assetContainer = AssetContainer::find($this->field->get('container'));

                    $transformer = new AssetsTransformer(
                        field: new Field('image', ['container' => $assetContainer->handle(), 'max_files' => 1]),
                        config: [
                            'related_field' => 'url',
                            'base_url' => $this->config['assets_base_url'] ?? null,
                            'download_when_missing' => $this->config['assets_download_when_missing'] ?? false,
                            'folder' => $this->config['assets_folder'] ?? null,
                            'process_downloaded_images' => $this->config['assets_process_downloaded_images'] ?? false,
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
            ->values()
            ->all();
    }

    private function enableBardButtons(): void
    {
        $buttons = [
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
        ];

        FieldUpdater::field($this->field)
            ->blueprint($this->blueprint)
            ->updateFieldConfig(array_merge($this->field->config(), ['buttons' => $buttons]));
    }

    private function isGutenbergValue(string $value): bool
    {
        return Str::contains($value, '<!-- wp:');
    }

    public function fieldItems(): array
    {
        $fieldItems = [];

        if ($assetContainer = $this->field->get('container')) {
            $assetContainer = AssetContainer::find($assetContainer);

            $fieldItems = [
                'assets_base_url' => [
                    'type' => 'text',
                    'display' => __('Assets Base URL'),
                    'instructions' => __('importer::messages.assets_base_url_instructions'),
                ],
                'assets_download_when_missing' => [
                    'type' => 'toggle',
                    'display' => __('Download assets when missing?'),
                    'instructions' => __('importer::messages.assets_download_when_missing_instructions'),
                    'width' => $assetContainer->sourcePreset() ? 50 : 100,
                ],
                'assets_process_downloaded_images' => [
                    'type' => 'toggle',
                    'display' => __('Process downloaded images?'),
                    'instructions' => __('importer::messages.assets_process_downloaded_images_instructions'),
                    'if' => ['assets_download_when_missing' => true],
                    'width' => 50,
                ],
                'assets_folder' => [
                    'type' => 'asset_folder',
                    'display' => __('Folder'),
                    'instructions' => __('importer::messages.assets_folder_instructions'),
                    'if' => ['assets_download_when_missing' => true],
                    'container' => $assetContainer->handle(),
                    'max_items' => 1,
                ],
            ];

            if (! $assetContainer->sourcePreset()) {
                unset($fieldItems['assets_process_downloaded_images']);
            }
        }

        return $fieldItems;
    }
}
