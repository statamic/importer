<?php

namespace Statamic\Importer\Transformers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Statamic\Assets\AssetUploader;
use Statamic\Contracts\Assets\AssetContainer as AssetContainerContract;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Path;
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

                $asset = Asset::make()
                    ->container($assetContainer)
                    ->path($this->assetPath($assetContainer, $path));

                $assetContainer->disk()->put($asset->path(), $request->body());

                $asset->save();
            }

            return $asset?->path();
        })->filter();

        return $this->field->get('max_files') === 1 ? $assets->first() : $assets->all();
    }

    private function assetPath(AssetContainerContract $assetContainer, string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        if (config('statamic.assets.lowercase')) {
            $ext = strtolower($extension);
        }

        $filename = AssetUploader::getSafeFilename(pathinfo($path, PATHINFO_FILENAME));

        $directory = $this->config('folder') ?? Str::beforeLast($path, '/');
        $directory = ($directory === '.') ? '/' : $directory;

        $path = Path::tidy($directory.'/'.$filename.'.'.$ext);
        $path = ltrim($path, '/');

        // If the file exists, we'll append a timestamp to prevent overwriting.
        if ($assetContainer->disk()->exists($path)) {
            $basename = $filename.'-'.Carbon::now()->timestamp.'.'.$ext;
            $path = Str::removeLeft(Path::assemble($directory, $basename), '/');
        }

        return $path;
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
                'validate' => 'required',
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
            'folder' => [
                'type' => 'asset_folder',
                'display' => __('Folder'),
                'instructions' => __('By default, downloaded assets will use same folder structure as the original URL. You can specify a different folder here.'),
                'if' => ['download_when_missing' => true],
                'container' => $this->field->get('container'),
                'max_items' => 1,
            ],
        ];
    }
}
