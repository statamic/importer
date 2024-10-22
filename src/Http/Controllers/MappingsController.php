<?php

namespace Statamic\Importer\Http\Controllers;

use Illuminate\Http\Request;
use Statamic\Facades\Collection;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\User;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Http\Controllers\CP\CpController;
use Statamic\Importer\Http\Requests\MappingsRequest;
use Statamic\Importer\Sources\Csv;
use Statamic\Importer\Sources\Xml;

class MappingsController extends CpController
{
    public function __invoke(MappingsRequest $request)
    {
        $blueprint = $this->getBlueprint($request);

        $row = match ($request->type) {
            'csv' => (new Csv([]))->getItems($request->path)->first(),
            'xml' => (new Xml([]))->getItems($request->path)->first(),
        };

        return [
            'item_options' => collect($row)->map(fn ($value, $key) => [
                'value' => $key,
                'label' => "<{$key}>: {$value}",
            ])->values(),
            'fields' => $blueprint->fields()->all()
                ->map(function (Field $field) {
                    return [
                        'type' => $field->type(),
                        'handle' => $field->handle(),
                        'display' => $field->display(),
                        'config' => $field->config(),
                    ];
                })
                ->when($request->destination['type'] === 'users', fn ($fields) => $fields->prepend(['handle' => 'email', 'fieldtype' => 'text', 'display' => __('Email')]))
                ->when($request->destination['type'] !== 'users', fn ($fields) => $fields->prepend(['handle' => 'slug', 'fieldtype' => 'text', 'display' => __('Slug')]))
                ->when($request->destination['type'] !== 'users', fn ($fields) => $fields->prepend(['handle' => 'title', 'fieldtype' => 'text', 'display' => __('Title')]))
                // todo: date?
                ->unique('handle')
                ->values(),
            'unique_keys' => $blueprint->fields()->all()
                ->filter(fn ($field) => in_array($field->type(), ['text', 'integer', 'slug']))
                ->map->handle()
                ->merge(['title', 'slug'])
                ->unique()
                ->values()
        ];
    }

    protected function getBlueprint(Request $request): Blueprint
    {
        if ($request->destination['type'] === 'entries') {
            return Collection::find($request->destination['collection'])->entryBlueprint();
        }

        if ($request->destination['type'] === 'terms') {
            return Taxonomy::find($request->destination['taxonomy'])->termBlueprint();
        }

        if ($request->destination['type'] === 'users') {
            return User::blueprint();
        }
    }

    protected function getRelatedFieldOptions(Field $field): array
    {
        if ($field->type() === 'entries') {
            return [
                'collections' => Collection::all()->map->handle()->values(),
            ];
        }

        if ($field->type() === 'terms') {
            return [
                'taxonomies' => Taxonomy::all()->map->handle()->values(),
            ];
        }

        if ($field->type() === 'users') {
            return [];
        }
    }
}
