<?php

namespace Statamic\Importer\Transformers;

use Facades\Statamic\Importer\Support\FieldUpdater;
use Illuminate\Support\Collection;
use Statamic\Facades\AssetContainer;
use Statamic\Fields\Field;
use Statamic\Fieldtypes\Bard\Augmentor as BardAugmentor;
use Statamic\Importer\Support\WordPress;
use Statamic\Importer\WordPress\Gutenberg;
use Statamic\Support\Str;

class BardTransformer extends AbstractTransformer
{
    public function transform(string $value): array
    {
        if ($this->isGutenbergValue($value)) {
            $value = Gutenberg::toBard(
                config: $this->config,
                blueprint: $this->blueprint,
                field: $this->field,
                value: $value
            );

            $this->enableBardButtons($value);

            return $value;
        }

        if ($this->config('wp_auto_p')) {
            $value = WordPress::wpautop($value);
        }

        $value = (new BardAugmentor($this->field->fieldtype()))->renderHtmlToProsemirror($value)['content'];

        $value = collect($value)
            ->map(fn($child) => $this->recursiveMap($child))
            ->filter()
            ->all();

        $this->enableBardButtons($value);

        return $value;
    }

    private function recursiveMap(array $node): ?array
    {
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

        if (isset($node['content'])) {
            $node['content'] = collect($node['content'])
                ->map(fn($child) => $this->recursiveMap($child))
                ->filter()
                ->all();
        }

        return $node;
    }

    private function enableBardButtons(array $value): void
    {
        $config = $this->field->config();

        $config['buttons'] = collect($config['buttons'] ?? [])
            ->merge($this->identifyBardButtons($value))
            ->unique()
            ->values()
            ->all();

        FieldUpdater::field($this->field)
            ->blueprint($this->blueprint)
            ->updateFieldConfig($config);
    }

    private function identifyBardButtons(array $value): Collection
    {
        $buttons = collect();

        collect($value)->each(function ($node) use (&$buttons) {
            $buttons->push(match ($node['type']) {
                'codeBlock' => 'codeblock',
                'horizontalRule' => 'horizontalrule',
                'image' => 'image',
                'blockquote' => 'quote',
                'orderedList' => 'orderedlist',
                'bulletList' => 'unorderedlist',
                'table' => 'table',
                default => null,
            });

            if ($node['type'] === 'heading' && isset($node['attrs']['level'])) {
                $buttons->push(match ($node['attrs']['level']) {
                    1 => 'h1',
                    2 => 'h2',
                    3 => 'h3',
                    4 => 'h4',
                    5 => 'h5',
                    6 => 'h6',
                    default => null,
                });
            }

            if (isset($node['attrs']['textAlign'])) {
                $buttons->push(match ($node['attrs']['textAlign']) {
                    'left' => 'alignleft',
                    'center' => 'aligncenter',
                    'right' => 'alignright',
                    'justify' => 'alignjustify',
                    default => null,
                });
            }

            if (isset($node['marks'])) {
                collect($node['marks'])->each(function ($mark) use (&$buttons) {
                    $buttons->push(match ($mark['type']) {
                        'link' => 'anchor',
                        'bold' => 'bold',
                        'italic' => 'italic',
                        'code' => 'code',
                        'underline' => 'underline',
                        'strike' => 'strikethrough',
                        'subscript' => 'subscript',
                        'superscript' => 'superscript',
                        'small' => 'small',
                        default => null,
                    });
                });
            }

            if ($node !== 'set' && isset($node['content'])) {
                $buttons = $buttons->merge($this->identifyBardButtons($node['content']));
            }
        });

        return $buttons->filter()->unique()->values();
    }

    private function isGutenbergValue(string $value): bool
    {
        return Str::contains($value, '<!-- wp:');
    }

    public function fieldItems(): array
    {
        $fieldItems = [
            'wp_auto_p' => [
                'type' => 'toggle',
                'display' => __('WordPress: Replace double line-breaks with <p> tags'),
                'instructions' => __('importer::messages.bard_wp_auto_p_instructions'),
            ],
        ];

        if ($assetContainer = $this->field->get('container')) {
            $assetContainer = AssetContainer::find($assetContainer);

            $fieldItems = [
                ...$fieldItems,
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
