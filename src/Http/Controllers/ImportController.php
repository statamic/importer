<?php

namespace Statamic\Importer\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Statamic\CP\Breadcrumbs;
use Statamic\Exceptions\NotFoundHttpException;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Site;
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
            'fields' => $blueprint->fields()->toPublishArray(),
            'meta' => $blueprint->fields()->meta(),
            'values' => $blueprint->fields()->preProcess()->values()->all(),
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
        $slug = Str::slug($request->name);

        $type = match (Storage::disk('local')->mimeType("statamic/file-uploads/{$request->file[0]}")) {
            'text/csv', 'application/csv', 'text/plain' => 'csv',
            'application/xml', 'text/xml' => 'xml',
        };

        Storage::disk('local')->move(
            from: "statamic/file-uploads/{$request->file[0]}",
            to: $path = "statamic/imports/{$slug}.{$type}"
        );

        $import = Import::make()
            ->id($slug)
            ->name($request->name)
            ->config([
                'type' => $type,
                'path' => Storage::disk('local')->path($path),
                'strategy' => [
                    'create' => in_array('create', $request->strategy),
                    'update' => in_array('update', $request->strategy),
                ],
                'destination' => collect([
                    'type' => $request->destination_type,
                    'collection' => Arr::first($request->destination_collection),
                    'taxonomy' => Arr::first($request->destination_taxonomy),
                    'site' => Arr::first($request->destination_site),
                ])->filter()->all(),
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
            'batchesTableMissing' => ! $this->ensureJobBatchesTableExists(),
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
            'unique_field' => $request->unique_field ?? 'slug',
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
        $blueprint = Blueprint::makeFromFields([
            'name' => [
                'type' => 'text',
                'display' => __('Name'),
                'instructions' => __('Name this import so you can identify it later.'),
                'validate' => 'required',
            ],
            'file' => [
                'type' => 'files',
                'display' => __('File'),
                'instructions' => __('Upload a CSV or XML file to import.'),
                'validate' => 'required',
                'max_files' => 1,
                'allowed_extensions' => [
                    'csv',
                    'xml',
                ],
            ],
            'destination_type' => [
                'type' => 'button_group',
                'display' => __('Data Type'),
                'instructions' => __('Choose what type of data are you importing'),
                'width' => 50,
                'options' => [
                    ['key' => 'entries', 'value' => __('Entries')],
                    ['key' => 'terms', 'value' => __('Terms')],
                    ['key' => 'users', 'value' => __('Users')],
                ],
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
            'destination_site' => [
                'type' => 'sites',
                'display' => __('Site'),
                'instructions' => __('Which site should the entries be imported into?'),
                'width' => 50,
                'max_items' => 1,
                'mode' => 'select',
                'if' => ['destination_type' => 'entries'],
                'validate' => 'required',
            ],
            'strategy' => [
                'type' => 'checkboxes',
                'display' => __('Import Strategy'),
                'instructions' => __('Choose what should happen when importing.'),
                'options' => [
                    ['key' => 'create', 'value' => __('Create new items')],
                    ['key' => 'update', 'value' => __('Update existing items')],
                ],
                'default' => ['create', 'update'],
            ],
        ]);

        if (! Site::hasMultiple()) {
            $blueprint->removeField('destination_site');
        }

        return $blueprint;
    }

    private function ensureJobBatchesTableExists(): bool
    {
        if (Schema::connection(config('queue.batching.database'))->hasTable(config('queue.batching.table'))) {
            return true;
        }

        if (app()->runningUnitTests() || app()->isProduction()) {
            return false;
        }

        try {
            // When this return a non-zero exit code, it doesn't necessarily mean there's an issue.
            // It could be because the migration has already been published.
            Artisan::call('make:queue-batches-table');

            if (Artisan::call('migrate') !== 0) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }
}
