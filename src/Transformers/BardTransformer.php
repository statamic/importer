<?php

namespace Statamic\Importer\Transformers;

use Illuminate\Support\Facades\Http;
use Statamic\Facades\AssetContainer;
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
                if ($this->field->get('container') && $node['type'] === 'image') {
                    $baseUrl = $this->config('assets_base_url');
                    $downloadWhenMissing = $this->config('assets_download_when_missing', false);
                    $assetContainer = AssetContainer::find($this->field->get('container'));

                    $path = Str::of($node['attrs']['src'])
                        ->after(Str::removeRight($baseUrl, '/'))
                        ->trim('/')
                        ->__toString();

                    $asset = $assetContainer->asset($path);

                    if (! $asset && $downloadWhenMissing) {
                        $request = Http::get(Str::removeRight($baseUrl, '/').Str::ensureLeft($path, '/'));

                        if (! $request->ok()) {
                            return null;
                        }

                        $assetContainer->disk()->put($path, $request->body());
                        $asset = $assetContainer->makeAsset($path);
                    }

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
