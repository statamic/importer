<?php

namespace Statamic\Importer\Imports;

use Illuminate\Bus\Batch;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Bus;
use Statamic\Facades\Collection;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\User;
use Statamic\Fields\Blueprint as StatamicBlueprint;
use Statamic\Fields\Fields;
use Statamic\Importer\Facades\Import as ImportFacade;
use Statamic\Importer\Importer;
use Statamic\Support\Traits\FluentlyGetsAndSets;

class Import
{
    use FluentlyGetsAndSets;

    public $id;
    public $name;
    public $config;
    public $batchIds;

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

    public function batchIds($batchIds = null)
    {
        return $this->fluentlyGetOrSet('batchIds')->args(func_get_args());
    }

    public function batches(): SupportCollection
    {
        return collect($this->batchIds())->map(fn ($id): Batch => Bus::findBatch($id));
    }

    public function allBatchesHaveFinished(): bool
    {
        return $this->batches()->every(fn (Batch $batch) => $batch->finished());
    }

    public function fileData(): array
    {
        return collect([
            'name' => $this->name(),
            'config' => $this->config()->all(),
            'batch_ids' => $this->batchIds(),
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

    public function blueprint(): StatamicBlueprint
    {
        return Blueprint::getBlueprint($this);
    }

    /**
     * Returns the blueprint of the destination collection, taxonomy, or user.
     */
    public function destinationBlueprint(): StatamicBlueprint
    {
        return match ($this->get('destination.type')) {
            'entries' => Collection::find($this->get('destination.collection'))
                ->entryBlueprints()
                ->when(
                    $this->get('destination.blueprint'),
                    fn ($collection) => $collection->filter(fn ($blueprint) => $blueprint->handle() === $this->get('destination.blueprint'))
                )
                ->first(),
            'terms' => Taxonomy::find($this->get('destination.taxonomy'))
                ->termBlueprints()
                ->when(
                    $this->get('destination.blueprint'),
                    fn ($taxonomy) => $taxonomy->filter(fn ($blueprint) => $blueprint->handle() === $this->get('destination.blueprint'))
                )
                ->first(),
            'users' => User::blueprint(),
        };
    }

    /**
     * Returns a Fields instance of the fields available for mapping.
     * Sometimes, additional fields will be appended, like "Published" for
     * entries, which doesn't exist as a blueprint field.
     */
    public function mappingFields(): Fields
    {
        $blueprint = clone $this->destinationBlueprint();

        if ($this->get('destination.type') === 'entries') {
            $blueprint->ensureField('published', [
                'type' => 'toggle',
                'display' => __('Published'),
            ]);
        }

        if ($this->get('destination.type') === 'terms') {
            $taxonomy = Taxonomy::find($this->get('destination.taxonomy'));

            if ($this->get('destination.site') !== $taxonomy->sites()->first()) {
                $blueprint->ensureField('default_slug', [
                    'type' => 'slug',
                    'display' => __('Slug in Default Site'),
                ]);
            }
        }

        return $blueprint->fields();
    }
}
