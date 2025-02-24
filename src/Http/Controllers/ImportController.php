<?php

namespace Statamic\Importer\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Statamic\CP\Breadcrumbs;
use Statamic\Facades;
use Statamic\Facades\Collection;
use Statamic\Facades\Taxonomy;
use Statamic\Fields\Blueprint;
use Statamic\Http\Controllers\CP\CpController;
use Statamic\Importer\Facades\Import as ImportFacade;
use Statamic\Importer\Http\Resources\Import as ImportResource;
use Statamic\Importer\Imports\Blueprint as ImportBlueprint;
use Statamic\Importer\Imports\Import;

class ImportController extends CpController
{
    use ExtractFromImportFields;

    public function index()
    {
        $blueprint = $this->createBlueprint();

        return view('importer::index', [
            'fields' => $blueprint->fields()->toPublishArray(),
            'meta' => $blueprint->fields()->meta(),
            'values' => $blueprint->fields()->preProcess()->values()->all(),
            'imports' => ImportFacade::all()
                ->map(function ($import): array {
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

    public function store(Request $request)
    {
        $blueprint = $this->createBlueprint();

        $data = $request->all();
        $id = Str::slug($request->name);

        $fields = $blueprint
            ->fields()
            ->addValues($data);

        $fields
            ->validator()
            ->validate();

        $file = $request->file[0];

        $type = match (Storage::disk('local')->mimeType("statamic/file-uploads/{$file}")) {
            'text/csv', 'application/csv', 'text/plain' => 'csv',
            'application/xml', 'text/xml' => 'xml',
        };

        Storage::disk('local')->move(
            from: "statamic/file-uploads/{$file}",
            to: $path = "statamic/imports/{$id}/".basename($file)
        );

        $path = Storage::disk('local')->path($path);

        $values = $fields
            ->process()
            ->values()
            ->all();

        $import = ImportFacade::make()
            ->id($id)
            ->name($request->name)
            ->config([
                'type' => $type,
                'path' => $path,
                'destination' => collect($values['destination'])->filter()->all(),
                'strategy' => $values['strategy'],
            ]);

        $saved = $import->save();

        return [
            'saved' => $saved,
            'redirect' => $import->editUrl(),
        ];
    }

    public function edit(Request $request, Import $import)
    {
        $blueprint = $import->blueprint();

        $fields = $blueprint
            ->fields()
            ->setParent($import)
            ->addValues($import->config()->merge([
                'name' => $import->name(),
                'file' => [basename($import->get('path'))],
            ])->all())
            ->preProcess();

        return view('importer::edit', [
            'import' => $import,
            'batchesTableMissing' => ! $this->ensureJobBatchesTableExists(),
            'breadcrumbs' => Breadcrumbs::make([
                ['text' => __('Imports'), 'url' => cp_route('utilities.importer')],
            ]),
            'title' => $import->name(),
            'values' => $fields->values()->all(),
            'meta' => $fields->meta(),
            'blueprint' => $blueprint->toPublishArray(),
        ]);
    }

    public function update(Request $request, Import $import)
    {
        $blueprint = $import->blueprint();

        $data = $request->except('id', 'run');

        $fields = $blueprint
            ->fields()
            ->setParent($import)
            ->addValues($data);

        $fields
            ->validator()
            ->validate();

        $type = $import->get('type');
        $path = $import->get('path');

        if (($request->file && $file = $request->file[0]) && $file !== basename($path)) {
            $type = match (Storage::disk('local')->mimeType("statamic/file-uploads/{$file}")) {
                'text/csv', 'application/csv', 'text/plain' => 'csv',
                'application/xml', 'text/xml' => 'xml',
            };

            Storage::disk('local')->move(
                from: "statamic/file-uploads/{$file}",
                to: $path = "statamic/imports/{$import->id()}/".basename($file)
            );

            $path = Storage::disk('local')->path($path);
        }

        $values = $fields
            ->process()
            ->values()
            ->all();

        $import
            ->name($request->name)
            ->config($import->config()->merge([
                'type' => $type,
                'path' => $path,
                'destination' => collect($values['destination'])->filter()->all(),
                'strategy' => $values['strategy'],
                'source' => $values['source'] ?? null,
                'mappings' => $values['mappings'],
                'unique_field' => $values['unique_field'] ?? null,
            ]));

        $saved = $import->save();

        if ($request->run) {
            $import->run();
        }

        // We need to refresh the blueprint after saving, so the field conditions are up-to-date.
        $blueprint = $import->blueprint();

        [$values, $meta] = $this->extractFromFields($import, $blueprint);

        return [
            'data' => array_merge((new ImportResource($import->fresh()))->resolve()['data'], [
                'values' => $values,
                'meta' => $meta,
                'blueprint' => $blueprint->setParent($import)->toPublishArray(),
            ]),
            'saved' => $saved,
        ];
    }

    public function destroy(Request $request, Import $import)
    {
        $import->delete();

        return [];
    }

    private function createBlueprint(): Blueprint
    {
        return Facades\Blueprint::make()->setContents([
            'fields' => Arr::get(ImportBlueprint::getBlueprint()->contents(), 'tabs.main.sections.0.fields'),
        ]);
    }

    private function ensureJobBatchesTableExists(): bool
    {
        try {
            if (Schema::connection(config('queue.batching.database', env('DB_CONNECTION', 'sqlite')))->hasTable(config('queue.batching.table', 'job_batches'))) {
                return true;
            }
        } catch (QueryException $e) {
            return false;
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
