<?php

namespace Statamic\Importer\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Facades\User;
use Statamic\Importer\Importer;
use Statamic\Importer\Imports\Import;

class ImportItemJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public Import $import, public array $item) {}

    public function handle(): void
    {
        $fields = $this->import->mappingFields();
        $blueprint = $this->import->destinationBlueprint();

        $data = collect($this->import->get('mappings'))
            ->reject(fn (array $mapping) => empty($mapping['key']))
            ->mapWithKeys(function (array $mapping, string $fieldHandle) use ($fields, $blueprint) {
                $field = $fields->get($fieldHandle);
                $value = Arr::get($this->item, $mapping['key']);

                if (is_null($value) || $value === '') {
                    return [$fieldHandle => null];
                }

                if ($transformer = Importer::getTransformer($field->type())) {
                    $value = (new $transformer($this->import, $blueprint, $field, $mapping, $this->item))->transform($value);
                }

                return [$fieldHandle => $value];
            })
            ->reject(fn ($value) => is_null($value))
            ->all();

        match ($this->import->get('destination.type')) {
            'entries' => $this->findOrCreateEntry($data),
            'terms' => $this->findOrCreateTerm($data),
            'users' => $this->findOrCreateUser($data),
        };
    }

    protected function findOrCreateEntry(array $data): void
    {
        $collection = Collection::find($this->import->get('destination.collection'));
        $site = Site::get($this->import->get('destination.site') ?? Site::default()->handle());

        $entry = Entry::query()
            ->where('locale', $site->handle())
            ->where('collection', $collection->handle())
            ->where($this->import->get('unique_field'), $data[$this->import->get('unique_field')])
            ->first();

        if (! $entry) {
            if (! in_array('create', $this->import->get('strategy'))) {
                return;
            }

            $entry = Entry::make()
                ->collection($collection)
                ->blueprint($this->import->get('destination.blueprint'))
                ->locale($site);
        }

        if ($entry->id() && ! in_array('update', $this->import->get('strategy'))) {
            return;
        }

        if (isset($data['slug'])) {
            $entry->slug(Arr::pull($data, 'slug'));
        }

        if (isset($data['published'])) {
            $entry->published(Arr::pull($data, 'published'));
        }

        if (isset($data['date'])) {
            $entry->date(Carbon::parse(Arr::pull($data, 'date')));
        }

        if ($structure = $collection->structure()) {
            $parent = Arr::pull($data, 'parent');

            $entry->afterSave(function ($entry) use ($structure, $site, $parent) {
                $tree = $structure->in($site->handle());

                if (! $tree->find($entry->id())) {
                    $tree->append($entry)->save();
                }

                if ($parent) {
                    $parents = Cache::get("importer.{$this->import->id()}.parents", []);
                    $parents[] = ['id' => $entry->id(), 'parent' => $parent];

                    Cache::forever("importer.{$this->import->id()}.parents", $parents);
                }
            });
        }

        $entry->merge($data);
        $entry->save();
    }

    protected function findOrCreateTerm(array $data): void
    {
        $term = Term::query()
            ->where('taxonomy', $this->import->get('destination.taxonomy'))
            ->where('id', $this->import->get('destination.taxonomy').'::'.Arr::get($data, 'default_slug', $data['slug']))
            ->first()
            ?->term();

        if (! $term) {
            if (! in_array('create', $this->import->get('strategy'))) {
                return;
            }

            $term = Term::make()
                ->taxonomy($this->import->get('destination.taxonomy'))
                ->blueprint($this->import->get('destination.blueprint'));
        }

        if (
            Term::find($term->id())?->in($this->import->get('destination.site') ?? Site::default()->handle())
            && ! in_array('update', $this->import->get('strategy'))
        ) {
            return;
        }

        $term = $term->in($this->import->get('destination.site') ?? Site::default()->handle());

        $term->slug(Arr::pull($data, 'slug'));

        $term->merge($data);

        $site = $this->import->get('destination.site') ?? Site::default()->handle();
        $defaultSite = Taxonomy::find($this->import->get('destination.taxonomy'))->sites()->first();

        // If the term is *not* being created in the default site, we'll copy all the
        // appropriate values into the default localization since it needs to exist.
        if (! Term::find($term->id()) && $site !== $defaultSite) {
            $term
                ->in($defaultSite)
                ->data($data)
                ->slug($data['default_slug'] ?? $term->slug());
        }

        $term->save();
    }

    protected function findOrCreateUser(array $data): void
    {
        $user = User::findByEmail($data['email']);

        if (! $user) {
            if (! in_array('create', $this->import->get('strategy'))) {
                return;
            }

            $user = User::make();
        }

        if ($user->id() && ! in_array('update', $this->import->get('strategy'))) {
            return;
        }

        if (isset($data['email'])) {
            $user->email(Arr::pull($data, 'email'));
        }

        if (isset($data['password'])) {
            $user->password(Arr::pull($data, 'password'));
        }

        $user->merge($data);
        $user->save();
    }
}
