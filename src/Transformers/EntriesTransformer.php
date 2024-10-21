<?php

namespace Statamic\Importer\Transformers;

use Statamic\Facades\Entry;
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

        if ($this->config('related_field') === 'id') {
            return is_string($value) ? explode('|', $value) : $value;
        }

        $entries = collect(explode('|', $value))->map(function ($value) {
            $entry = Entry::query()
                ->whereIn('collection', Arr::wrap($this->field->get('collections')))
                ->where($this->config('related_field'), $value)
                ->first();

            if (! $entry && $this->config('create_when_missing')) {
                $entry = Entry::make()->collection(Arr::first($this->field->get('collections')));

                if ($this->config('related_field') === 'slug') {
                    $entry->slug($value);
                } else {
                    $entry->set($this->config('related_field'), $value);
                }

                $entry->save();

                return $entry->id();
            }

            return $entry?->id();
        })->filter();

        return $this->field->get('max_items') === 1 ? $entries->first() : $entries->all();
    }
}
