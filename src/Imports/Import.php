<?php

namespace Statamic\Importer\Imports;

use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Statamic\Facades\Collection;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\User;
use Statamic\Importer\Facades\Import as ImportFacade;
use Statamic\Importer\Importer;
use Statamic\Support\Traits\FluentlyGetsAndSets;

class Import
{
    use FluentlyGetsAndSets;

    public $id;
    public $name;
    public $config;
    public $batchId;

    public function __construct()
    {
        $this->config = collect();
    }

    public function id($id = null)
    {
        return $this->fluentlyGetOrSet('id')->args(func_get_args());
    }

    public function name($name = null)
    {
        return $this->fluentlyGetOrSet('name')->args(func_get_args());
    }

    public function config($config = null)
    {
        return $this
            ->fluentlyGetOrSet('config')
            ->getter(function ($config) {
                return $config ?? collect();
            })
            ->setter(function ($config) {
                if (is_array($config)) {
                    $config = collect($config);
                }

                return $config;
            })
            ->args(func_get_args());
    }

    public function get(string $key, ?string $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    public function batchId($batchId = null)
    {
        return $this->fluentlyGetOrSet('batchId')->args(func_get_args());
    }

    public function batch(): ?PendingBatch
    {
        if (! $this->batchId()) {
            return null;
        }

        return Bus::batch($this->batchId());
    }

    public function fileData(): array
    {
        return collect([
            'name' => $this->name(),
            'config' => $this->config()->all(),
            'batch_id' => $this->batchId(),
        ])->filter()->all();
    }

    public function path(): string
    {
        return ImportFacade::path()."/{$this->id()}.yaml";
    }

    public function fresh(): self
    {
        return ImportFacade::find($this->id());
    }

    public function save(): bool
    {
        ImportFacade::save($this);

        return true;
    }

    public function delete(): bool
    {
        ImportFacade::delete($this);

        return true;
    }

    public function run(): void
    {
        Importer::run($this);
    }

    public function editUrl(): string
    {
        return cp_route('utilities.importer.edit', $this->id());
    }

    public function updateUrl(): string
    {
        return cp_route('utilities.importer.update', $this->id());
    }

    public function deleteUrl(): string
    {
        return cp_route('utilities.importer.destroy', $this->id());
    }

    public function blueprint(): \Statamic\Fields\Blueprint
    {
        return Blueprint::getBlueprint($this);
    }

    public function destinationBlueprint(): \Statamic\Fields\Blueprint
    {
        if ($this->get('destination.type') === 'entries') {
            return Collection::find($this->get('destination.collection'))->entryBlueprint();
        }

        if ($this->get('destination.type') === 'terms') {
            return Taxonomy::find($this->get('destination.taxonomy'))->termBlueprint();
        }

        if ($this->get('destination.type') === 'users') {
            return User::blueprint();
        }
    }
}
