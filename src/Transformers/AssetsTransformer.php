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
                $asset = $assetContainer->makeAsset($path);
            }

            return $asset?->path();
        })->filter();

        return $this->field->get('max_files') === 1 ? $assets->first() : $assets->all();
    }
}
