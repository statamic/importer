<?php

namespace Statamic\Importer\Transformers;

use Facades\Statamic\Imaging\ImageValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Statamic\Assets\AssetUploader;
use Statamic\Contracts\Assets\AssetContainer as AssetContainerContract;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Glide;
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

        $assetAlts = $this->config('alt')
            ? collect(explode('|', Arr::get($this->values, $this->config('alt'))))
            : null;

        $assets = collect(explode('|', $value))->map(function ($path, $index) use ($assetContainer, $relatedField, $baseUrl, $assetAlts) {
            $path = Str::of($path)
                ->when($relatedField === 'url' && $baseUrl, function ($str) use ($baseUrl) {
                    return $str->after($baseUrl);
                })
                ->trim('/')
                ->__toString();

            $assetPath = $path;

            if ($this->config('folder')) {
                $assetPath = Str::ensureRight($this->config('folder'), '/').Str::afterLast($path, '/');
            }

            $asset = $assetContainer->asset($assetPath);

            if (! $asset && $this->config('download_when_missing') && $relatedField === 'url') {
                $request = Http::get(Str::removeRight($baseUrl, '/').Str::ensureLeft($path, '/'));

                if (! $request->ok()) {
                    return null;
                }

                $asset = Asset::make()
                    ->container($assetContainer)
                    ->path($this->assetPath($assetContainer, $path));

                $this->config('process_downloaded_images')
                    ? $this->processAssetUsingSourcePreset($asset, $path, $request->body())
                    : $assetContainer->disk()->put($asset->path(), $request->body());

                $asset->save();
            }

            if ($assetAlts) {
                $asset?->set('alt', $assetAlts->get($index))->save();
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

    private function processAssetUsingSourcePreset($asset, string $path, string $contents): void
    {
        Storage::disk('local')->put($tempPath = 'statamic/temp-assets/'.Str::random().'.'.Str::afterLast($path, '.'), $contents);

        $uploadedFile = new UploadedFile(
            path: Storage::disk('local')->path($tempPath),
            originalName: $asset->basename(),
            mimeType: Storage::mimeType($tempPath)
        );

        $source = $this->processSourceFile($uploadedFile);

        $asset->container()->disk()->put($asset->path(), $stream = fopen($source, 'r'));

        if (is_resource($stream)) {
            fclose($stream);
        }

        app('files')->delete($source);
        Storage::disk('local')->delete($tempPath);
    }

    /**
     * This method has been copied from Core's Uploader class. We don't need the rest of the
     * Uploader, just this method so copying it was the easiest solution.
     */
    private function processSourceFile(UploadedFile $file): string
    {
        if ($file->getMimeType() === 'image/gif') {
            return $file->getRealPath();
        }

        if (! $preset = $this->preset()) {
            return $file->getRealPath();
        }

        if (! ImageValidator::isValidImage($file->getClientOriginalExtension(), $file->getClientMimeType())) {
            return $file->getRealPath();
        }

        $server = Glide::server([
            'source' => $file->getPath(),
            'cache' => $cache = storage_path('statamic/glide/tmp'),
            'cache_with_file_extensions' => false,
        ]);

        try {
            return $cache.'/'.$server->makeImage($file->getFilename(), ['p' => $preset]);
        } catch (\Exception $exception) {
            return $file->getRealPath();
        }
    }

    private function preset()
    {
        return AssetContainer::find($this->field->get('container'))->sourcePreset();
    }

    public function fieldItems(): array
    {
        $assetContainer = AssetContainer::find($this->field->get('container'));

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
                'width' => $assetContainer->sourcePreset() ? 50 : 100,
            ],
            'process_downloaded_images' => [
                'type' => 'toggle',
                'display' => __('Process downloaded images?'),
                'instructions' => __('importer::messages.assets_process_downloaded_images_instructions'),
                'if' => ['related_field' => 'url', 'download_when_missing' => true],
                'width' => 50,
            ],
            'folder' => [
                'type' => 'asset_folder',
                'display' => __('Folder'),
                'instructions' => __('importer::messages.assets_folder_instructions'),
                'if' => ['download_when_missing' => true],
                'container' => $assetContainer->handle(),
                'max_items' => 1,
            ],
        ];

        if ($assetContainer->blueprint()->hasField('alt')) {
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

        if (! $assetContainer->sourcePreset()) {
            unset($fieldItems['process_downloaded_images']);
        }

        return $fieldItems;
    }
}
