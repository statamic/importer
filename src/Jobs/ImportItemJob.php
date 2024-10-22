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

class ImportItemJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public array $config, public array $item) {}

    public function handle(): void
    {
        $blueprint = $this->getBlueprint();

        $data = collect($this->config('mappings'))
            ->reject(fn ($mapping) => empty($mapping['key']))
            ->mapWithKeys(function (array $mapping, string $fieldHandle) use ($blueprint) {
                $value = Arr::get($this->item, $mapping['key']);
                $field = $blueprint->field($fieldHandle);

                if (! $value) {
                    return [$fieldHandle => null];
                }

                $transformer = Importer::getTransformer($field->type());
                $value = $transformer ? (new $transformer($blueprint, $field, $mapping))->transform($value) : $value;

                return [$fieldHandle => $value];
            })
            ->filter()
            ->all();

        match ($this->config('destination.type')) {
            'entries' => $this->findOrCreateEntry($data),
            'terms' => $this->findOrCreateTerm($data),
            'users' => $this->findOrCreateUser($data),
        };
    }

    protected function getBlueprint(): Blueprint
    {
        if ($this->config('destination.type') === 'entries') {
            return Collection::find($this->config('destination.collection'))->entryBlueprint();
        }

        if ($this->config('destination.type') === 'terms') {
            return Taxonomy::find($this->config('destination.taxonomy'))->termBlueprint();
        }

        if ($this->config('destination.type') === 'users') {
            return User::blueprint();
        }
    }

    protected function findOrCreateEntry(array $data): void
    {
        $entry = Entry::query()
            ->where('collection', $this->config('destination.collection'))
            ->where($this->config('unique_key'), $data[$this->config('unique_key')])
            ->first();

        if (! $entry) {
            $entry = Entry::make()->collection($this->config('destination.collection'));
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
            ->where('taxonomy', $this->config('destination.taxonomy'))
            ->where($this->config('unique_key'), $data[$this->config('unique_key')])
            ->first();

        if (! $term) {
            $term = Term::make()->taxonomy($this->config('destination.taxonomy'));
        }

        if (isset($data['slug'])) {
            $term->slug(Arr::pull($data, 'slug'));
        }

        if (! $term->slug()) {
            $term->slug(Str::slug($data[$this->config('unique_key')]));
        }

        $term->merge($data);

        $term->save();
    }

    protected function findOrCreateUser(array $data): void
    {
        $user = User::query()
            ->where($this->config('unique_key'), $data[$this->config('unique_key')])
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

    protected function config(?string $key = null, $default = null): mixed
    {
        if (is_null($key)) {
            return collect($this->config);
        }

        return Arr::get($this->config, $key, $default);
    }
}
