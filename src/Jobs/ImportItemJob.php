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
use Illuminate\Support\Str;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Facades\User;
use Statamic\Fields\Blueprint;
use Statamic\Importer\Importer;
use Statamic\Importer\Imports\Import;

class ImportItemJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public Import $import, public array $item) {}

    public function handle(): void
    {
        $blueprint = $this->getBlueprint();

        $data = collect($this->import->get('mappings'))
            ->reject(fn (array $mapping) => empty($mapping['key']))
            ->mapWithKeys(function (array $mapping, string $fieldHandle) use ($blueprint) {
                $value = Arr::get($this->item, $mapping['key']);
                $field = $blueprint->field($fieldHandle);

                if (! $value) {
                    return [$fieldHandle => null];
                }

                if ($transformer = Importer::getTransformer($field->type())) {
                    $value = (new $transformer($this->import, $blueprint, $field, $mapping))->transform($value);
                }

                return [$fieldHandle => $value];
            })
            ->filter()
            ->all();

        match ($this->import->get('destination.type')) {
            'entries' => $this->findOrCreateEntry($data),
            'terms' => $this->findOrCreateTerm($data),
            'users' => $this->findOrCreateUser($data),
        };
    }

    protected function getBlueprint(): Blueprint
    {
        if ($this->import->get('destination.type') === 'entries') {
            return Collection::find($this->import->get('destination.collection'))->entryBlueprint();
        }

        if ($this->import->get('destination.type') === 'terms') {
            return Taxonomy::find($this->import->get('destination.taxonomy'))->termBlueprint();
        }

        if ($this->import->get('destination.type') === 'users') {
            return User::blueprint();
        }
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
            if (! $this->import->get('strategy.create', true)) {
                return;
            }

            $entry = Entry::make()->collection($collection)->locale($site);
        }

        if ($entry->id() && ! $this->import->get('strategy.update', true)) {
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
            ->where($this->import->get('unique_field'), $data[$this->import->get('unique_field')])
            ->first();

        if (! $term) {
            if (! $this->import->get('strategy.create', true)) {
                return;
            }

            $term = Term::make()->taxonomy($this->import->get('destination.taxonomy'));
        }

        if (Term::find($term->id()) && ! $this->import->get('strategy.update', true)) {
            return;
        }

        if (isset($data['slug'])) {
            $term->slug(Arr::pull($data, 'slug'));
        }

        if (! $term->slug()) {
            $term->slug(Str::slug($data[$this->import->get('unique_field')]));
        }

        $term->merge($data);

        $term->save();
    }

    protected function findOrCreateUser(array $data): void
    {
        $user = User::query()
            ->where($this->import->get('unique_field'), $data[$this->import->get('unique_field')])
            ->first();

        if (! $user) {
            if (! $this->import->get('strategy.create', true)) {
                return;
            }

            $user = User::make();
        }

        if ($user->id() && ! $this->import->get('strategy.update', true)) {
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
