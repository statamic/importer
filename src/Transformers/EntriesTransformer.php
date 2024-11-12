<?php

namespace Statamic\Importer\Transformers;

use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Support\Arr;
use Statamic\Support\Str;

class EntriesTransformer extends AbstractTransformer
{
    public function transform(string $value): null|string|array
    {
        // When $value is a serialized array, deserialize it.
        if (Str::startsWith($value, 'a:')) {
            $value = collect(unserialize($value))->join('|');
        }

        // When $value is a JSON string, decode it.
        if (Str::startsWith($value, ['{', '[']) || Str::startsWith($value, ['[', ']'])) {
            $value = collect(json_decode($value, true))->join('|');
        }

        if ($this->config('related_field') === 'id') {
            return is_string($value) ? explode('|', $value) : $value;
        }

        $entries = collect(explode('|', $value))->map(function ($value) {
            $entry = Entry::query()
                ->whereIn('collection', Arr::wrap($this->field->get('collections')))
                ->when(! $this->field->get('select_across_sites'), function ($query) {
                    $query->where('locale', $this->import->get('destination.site') ?? Site::default()->handle());
                })
                ->where($this->config('related_field'), $value)
                ->first();

            if (! $entry && $this->config('create_when_missing')) {
                $entry = Entry::make()
                    ->collection(Arr::first($this->field->get('collections')))
                    ->locale($this->import->get('destination.site') ?? Site::default()->handle());

                if ($this->config('related_field') === 'slug') {
                    $entry->slug($value);
                } else {
                    $entry->set($this->config('related_field'), $value);
                }

                if ($structure = $entry->collection()->structure()) {
                    $entry->afterSave(function ($entry) use ($structure) {
                        $tree = $structure->in($entry->site()->handle());

                        if (! $tree->find($entry->id())) {
                            $tree->append($entry)->save();
                        }
                    });
                }

                $entry->save();

                return $entry->id();
            }

            return $entry?->id();
        })->filter();

        return $this->field->get('max_items') === 1 ? $entries->first() : $entries->all();
    }

    public function fieldItems(): array
    {
        $collections = $this->field->get('collections') ?? Collection::all()->map->handle();

        $fields = collect($collections)
            ->flatMap(fn (string $collection) => Collection::find($collection)->entryBlueprints())
            ->flatMap(fn ($blueprint) => $blueprint->fields()->all())
            ->unique(fn ($field) => $field->handle());

        return [
            'related_field' => [
                'type' => 'select',
                'display' => __('Related Field'),
                'instructions' => __('Which field does the data reference?'),
                'default' => 'id',
                'options' => $fields
                    ->map(fn ($field) => ['key' => $field->handle(), 'value' => $field->display()])
                    ->prepend(['key' => 'id', 'value' => __('ID')])
                    ->values()
                    ->all(),
                'validate' => 'required',
            ],
            'create_when_missing' => [
                'type' => 'toggle',
                'display' => __('Create entry when missing?'),
                'instructions' => __("Create the entry if it doesn't exist."),
                'default' => false,
            ],
        ];
    }
}
