<?php

namespace Statamic\Importer\Transformers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Statamic\Assets\AssetUploader;
use Statamic\Contracts\Assets\AssetContainer as AssetContainerContract;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Path;
use Statamic\Importer\Sources\Csv;
use Statamic\Importer\Sources\Xml;
use Statamic\Support\Arr;
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

        // When $value is a JSON string, decode it.
        if (Str::startsWith($value, ['{', '[']) || Str::startsWith($value, ['[', ']'])) {
            $value = collect(json_decode($value, true))->join('|');
        }

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

            if ($alt = $this->config('alt')) {
                $asset?->set('alt', Arr::get($this->item, $alt))->save();
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
        $fieldItems = [
            'related_field' => [
                'type' => 'select',
                'display' => __('Related Field'),
                'instructions' => __('importer::messages.assets_related_field_instructions'),
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
                'instructions' => __('importer::messages.assets_base_url_instructions'),
                'if' => ['related_field' => 'url'],
            ],
            'download_when_missing' => [
                'type' => 'toggle',
                'display' => __('Download when missing?'),
                'instructions' => __('importer::messages.assets_download_when_missing_instructions'),
                'if' => ['related_field' => 'url'],
            ],
            'folder' => [
                'type' => 'asset_folder',
                'display' => __('Folder'),
                'instructions' => __('importer::messages.assets_folder_instructions'),
                'if' => ['download_when_missing' => true],
                'container' => $this->field->get('container'),
                'max_items' => 1,
            ],
        ];

        if (AssetContainer::find($this->field->get('container'))->blueprint()->hasField('alt')) {
            $row = match ($this->import?->get('type')) {
                'csv' => (new Csv($this->import))->getItems($this->import->get('path'))->first(),
                'xml' => (new Xml($this->import))->getItems($this->import->get('path'))->first(),
            };

            $fieldItems['alt'] = [
                'type' => 'select',
                'display' => __('Alt Text'),
                'instructions' => __('importer::messages.assets_alt_instructions'),
                'options' => collect($row)->map(fn ($value, $key) => [
                    'key' => $key,
                    'value' => "<{$key}>: ".Str::truncate($value, 200),
                ])->values()->all(),
            ];
        }

        return $fieldItems;
    }
}
