<?php

namespace Statamic\Importer\Transformers;

use Statamic\Facades\AssetContainer;
use Statamic\Facades\Fieldset;
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

        $importedField = $this->blueprint->fields()->items()
            ->where('handle', $this->field->handle())
            ->filter(fn (array $field) => isset($field['field']) && is_string($field['field']))
            ->first();

        if ($importedField) {
            /** @var \Statamic\Fields\Fieldset $fieldset */
            $fieldHandle = Str::after($importedField['field'], '.');
            $fieldset = Fieldset::find(Str::before($importedField['field'], '.'));

            $fieldset->setContents([
                ...$fieldset->contents(),
                'fields' => collect($fieldset->contents()['fields'])
                    ->map(function (array $field) use ($buttons, $fieldHandle) {
                        if ($field['handle'] === $fieldHandle) {
                            return [
                                'handle' => $field['handle'],
                                'field' => array_merge($field['field'], ['buttons' => $buttons]),
                            ];
                        }

                        return $field;
                    })
                    ->all(),
            ])->save();

            return;
        }

        if ($prefix = $this->field->prefix()) {
            /** @var \Statamic\Fields\Fieldset $fieldset */
            $fieldset = $this->blueprint->fields()->items()
                ->filter(fn (array $field) => isset($field['import']))
                ->map(fn (array $field) => Fieldset::find($field['import']))
                ->filter(function ($fieldset) use ($prefix) {
                    return collect($fieldset->fields()->items())
                        ->where('handle', Str::after($this->field->handle(), $prefix))
                        ->isNotEmpty();
                })
                ->first();

            $fieldset->setContents([
                ...$fieldset->contents(),
                'fields' => collect($fieldset->contents()['fields'])
                    ->map(function (array $field) use ($buttons, $prefix) {
                        if ($field['handle'] === Str::after($this->field->handle(), $prefix)) {
                            return [
                                'handle' => $field['handle'],
                                'field' => array_merge($field['field'], ['buttons' => $buttons]),
                            ];
                        }

                        return $field;
                    })
                    ->all(),
            ])->save();

            return;
        }

        $this->blueprint->ensureFieldHasConfig(
            handle: $this->field->handle(),
            config: array_merge($this->field->config(), ['buttons' => $buttons])
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
