<?php

namespace Statamic\Importer\Transformers;

use Illuminate\Support\Facades\Http;
use Statamic\Facades\AssetContainer;
use Statamic\Support\Str;

class AssetsTransformer extends AbstractTransformer
{
    public function transform(string $value): null|string|array
    {
        $assetContainer = $this->field->get('container')
            ? AssetContainer::find($this->field->get('container'))
            : AssetContainer::all()->first();

        $baseUrl = $this->config('base_url');
        $relatedField = $this->config('related_field', 'path');

        $assets = collect(explode('|', $value))->map(function ($path) use ($assetContainer, $relatedField, $baseUrl) {
            $path = Str::of($path)
                ->when($relatedField === 'url' && $baseUrl, function ($str) use ($baseUrl) {
                    return $str->after($baseUrl);
                })
                ->trim('/')
                ->__toString();

            $asset = $assetContainer->asset($path);

            if (! $asset && $this->config('download_when_missing') && $relatedField === 'url') {
                $request = Http::get(Str::removeRight($baseUrl, '/').Str::ensureLeft($path, '/'));

                if (! $request->ok()) {
                    return null;
                }

                $assetContainer->disk()->put($path, $request->body());
                $asset = tap($assetContainer->makeAsset($path))->save();
            }

            return $asset?->path();
        })->filter();

        return $this->field->get('max_files') === 1 ? $assets->first() : $assets->all();
    }

    public function fieldItems(): array
    {
        return [
            'related_field' => [
                'type' => 'select',
                'display' => __('Related Field'),
                'instructions' => __('Which field does the data reference?'),
                'default' => 'url',
                'options' => [
                    ['key' => 'path', 'value' => __('Path')],
                    ['key' => 'url', 'value' => __('URL')],
                ],
            ],
            'base_url' => [
                'type' => 'text',
                'display' => __('Base URL'),
                'instructions' => __('The base URL to prepend to the path.'),
                'if' => ['related_field' => 'url'],
            ],
            'download_when_missing' => [
                'type' => 'toggle',
                'display' => __('Download when missing?'),
                'instructions' => __("If the asset can't be found in the asset container, should it be downloaded?"),
                'if' => ['related_field' => 'url'],
            ],
        ];
    }
}
