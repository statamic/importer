<?php

namespace Statamic\Importer\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Statamic\CP\Breadcrumbs;
use Statamic\Exceptions\NotFoundHttpException;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Taxonomy;
use Statamic\Http\Controllers\CP\CpController;
use Statamic\Importer\Facades\Import;
use Statamic\Importer\Http\Requests\CreateImportRequest;
use Statamic\Importer\Http\Requests\UpdateImportRequest;

class ImportController extends CpController
{
    public function index()
    {
        $blueprint = $this->getConfigBlueprint();

        return view('importer::index', [
            'blueprint' => $blueprint->toPublishArray(),
            'fields' => $blueprint->fields()->toPublishArray(),
            'meta' => $blueprint->fields()->meta(),
            'values' => $blueprint->fields()->values()->all(),
            'imports' => Import::all()
                ->map(function ($import) {
                    $destination = match ($import->get('destination.type')) {
                        'entries' => __('Entries (:collection)', ['collection' => Collection::find($import->get('destination.collection'))?->title()]),
                        'terms' => __('Terms (:taxonomy)', ['taxonomy' => Taxonomy::find($import->get('destination.taxonomy'))?->title()]),
                        'users' => __('Users'),
                    };

                    return [
                        'id' => $import->id(),
                        'name' => $import->name(),
                        'type' => $import->get('type'),
                        'destination' => $destination,
                        'edit_url' => $import->editUrl(),
                        'delete_url' => $import->deleteUrl(),
                    ];
                }),
        ]);
    }

    public function store(CreateImportRequest $request)
    {
        $import = Import::make()
            ->name($request->name)
            ->config([
                'type' => $request->type,
                'path' => $request->path,
                'destination' => [
                    'type' => $request->destination_type,
                    'collection' => Arr::first($request->destination_collection),
                    'taxonomy' => Arr::first($request->destination_taxonomy),
                ],
            ]);

        $import->save();

        return [
            'redirect' => $import->editUrl(),
        ];
    }

    public function edit(Request $request, $import)
    {
        $import = Import::find($import);

        throw_unless($import, new NotFoundHttpException);

        return view('importer::edit', [
            'import' => $import,
            'breadcrumbs' => Breadcrumbs::make([
                ['text' => __('Imports'), 'url' => cp_route('utilities.importer')],
            ]),
        ]);
    }

    public function update(UpdateImportRequest $request, $import)
    {
        $import = Import::find($import);

        throw_unless($import, new NotFoundHttpException);

        $import->config($import->config()->merge([
            'mappings' => $request->mappings,
            'unique_key' => $request->unique_key ?? 'slug',
        ]));

        $import->save();

        if ($request->run) {
            $import->run();
        }

        return [];
    }

    public function destroy(Request $request, $import)
    {
        $import = Import::find($import);

        throw_unless($import, new NotFoundHttpException);

        $import->delete();

        return [];
    }

    private function getConfigBlueprint(): \Statamic\Fields\Blueprint
    {
        return Blueprint::makeFromFields([
            'name' => [
                'type' => 'text',
                'display' => __('Name'),
                'validate' => 'required',
            ],
            'type' => [
                'type' => 'select',
                'display' => __('Type'),
                'width' => 25,
                'options' => [
                    ['key' => 'xml', 'value' => __('XML')],
                    ['key' => 'csv', 'value' => __('CSV')],
                ],
                'validate' => 'required',
            ],
            'path' => [
                'type' => 'text',
                'display' => __('Path'),
                'instructions' => __("The absolute path to the file, whether it's on the local filesystem or a URL."),
                'width' => 75,
                'validate' => 'required',
            ],
            'destination_type' => [
                'type' => 'select',
                'display' => __('Destination'),
                'instructions' => __('What are you importing?'),
                'options' => [
                    ['key' => 'entries', 'value' => __('Entries')],
                    ['key' => 'terms', 'value' => __('Terms')],
                    ['key' => 'users', 'value' => __('Users')],
                ],
                'width' => 50,
                'validate' => 'required',
            ],
            'destination_collection' => [
                'type' => 'collections',
                'display' => __('Collection'),
                'instructions' => __('Select the collection to import entries into.'),
                'width' => 50,
                'max_items' => 1,
                'mode' => 'select',
                'if' => ['destination_type' => 'entries'],
                'validate' => 'required',
            ],
            'destination_taxonomy' => [
                'type' => 'taxonomies',
                'display' => __('Taxonomy'),
                'instructions' => __('Select the taxonomy to import terms into.'),
                'width' => 50,
                'max_items' => 1,
                'mode' => 'select',
                'if' => ['destination_type' => 'terms'],
                'validate' => 'required',
            ],
        ]);
    }
}
