<?php

namespace Statamic\Importer\Imports;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Statamic\Facades\YAML;

class ImportRepository
{
    public function all(): Collection
    {
        File::ensureDirectoryExists($this->path());

        return collect(File::allFiles($this->path()))
            ->map(function (string $path) {
                $id = basename($path, '.yaml');
                $data = YAML::file($path)->parse();

                return $this->make()
                    ->id($id)
                    ->name(Arr::pull($data, 'name'))
                    ->config(Arr::pull($data, 'config'));
            })
            ->sortBy->name()
            ->values();
    }

    public function find(string $id): ?Import
    {
        return $this->all()->where('id', $id)->first();
    }

    public function make(): Import
    {
        return app(Import::class);
    }

    public function save(Import $import): void
    {
        if (! $import->id()) {
            $import->id(Str::slug($import->name(), '_'));
        }

        File::ensureDirectoryExists($this->path());

        File::put($import->path(), YAML::dump($import->fileData()));
    }

    public function delete(Import $import): void
    {
        File::delete($import->path());

        if (Storage::disk('local')->exists($import->path())) {
            Storage::disk('local')->delete($import->path());
        }
    }

    public function path(): string
    {
        return storage_path('statamic/importer');
    }
}
