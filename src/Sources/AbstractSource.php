<?php

namespace Statamic\Importer\Sources;

use Illuminate\Support\Arr;
use Illuminate\Support\LazyCollection;
use Statamic\Extend\HasFields;
use Statamic\Importer\Contracts\Source;
use Statamic\Importer\Imports\Import;

abstract class AbstractSource implements Source
{
    use HasFields;

    public function __construct(public ?Import $import = null) {}

    abstract public function getItems(string $path): LazyCollection;

    public function fieldItems(): array
    {
        return [];
    }

    protected function config(?string $key = null, $default = null): mixed
    {
        if (is_null($key)) {
            return collect($this->import->get('source'));
        }

        return Arr::get($this->import->get('source'), $key, $default);
    }
}
