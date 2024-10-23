<?php

namespace Statamic\Importer\Transformers;

use Illuminate\Support\Arr;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Support\Str;

class TermsTransformer extends AbstractTransformer
{
    public function transform(string $value): null|string|array
    {
        $terms = collect(explode('|', $value))->map(function ($value) {
            $term = Term::query()
                ->whereIn('taxonomy', Arr::wrap($this->field->get('taxonomies')))
                ->where($this->config('related_field'), $value)
                ->first();

            if (! $term && $this->config('create_when_missing')) {
                $term = Term::make()->taxonomy(Arr::first($this->field->get('taxonomies')));

                if ($this->config('related_field') === 'slug') {
                    $term->slug($value)->set('title', $value);
                } else {
                    $term->set($this->config('related_field'), $value)->slug(Str::slug($value));
                }

                $term->save();

                return $term->id();
            }

            return $term?->id();
        })->filter();

        return $this->field->get('max_items') === 1 ? $terms->first() : $terms->all();
    }

    public function fieldItems(): array
    {
        $taxonomies = $this->field->get('taxonomies') ?? Taxonomy::all()->map->handle();

        $fields = collect($taxonomies)
            ->flatMap(fn (string $collection) => Taxonomy::find($collection)->termBlueprints())
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
            ],
            'create_when_missing' => [
                'type' => 'toggle',
                'display' => __('Create term when missing?'),
                'instructions' => __("Create the term if it doesn't exist."),
                'default' => false,
            ],
        ];
    }
}
