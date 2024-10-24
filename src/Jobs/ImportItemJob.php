<?php

namespace Statamic\Importer\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Facades\User;
use Statamic\Fields\Blueprint;
use Statamic\Importer\Importer;
use Statamic\Importer\Imports\Import;

class ImportItemJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

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
                    $value = (new $transformer($blueprint, $field, $mapping))->transform($value);
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
        $entry = Entry::query()
            ->where('collection', $this->import->get('destination.collection'))
            ->where($this->import->get('unique_field'), $data[$this->import->get('unique_field')])
            ->first();

        if (! $entry) {
            $entry = Entry::make()->collection($this->import->get('destination.collection'));
        }

        if (isset($data['slug'])) {
            $entry->slug(Arr::pull($data, 'slug'));
        }

        if (isset($data['published'])) {
            $entry->published(Arr::pull($data, 'published'));
        }

        if (isset($data['date'])) {
            $entry->date(Arr::pull($data, 'date'));
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
            $term = Term::make()->taxonomy($this->import->get('destination.taxonomy'));
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
            $user = User::make();
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
