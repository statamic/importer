<?php

namespace Statamic\Importer\Http\Controllers;

use Statamic\Facades\Collection;
use Statamic\Facades\Taxonomy;
use Statamic\Http\Controllers\CP\CpController;
use Statamic\Importer\Http\Requests\ImportRequest;
use Statamic\Importer\Importer;

class ImportController extends CpController
{
    public function index()
    {
        return view('importer::index', [
            'mappingsUrl' => cp_route('utilities.import.mappings'),
            'collections' => Collection::all()
                ->map(fn ($collection) => ['value' => $collection->handle(), 'label' => $collection->title()])
                ->values(),
            'taxonomies' => Taxonomy::all()
                ->map(fn ($taxonomy) => ['value' => $taxonomy->handle(), 'label' => $taxonomy->title()])
                ->values(),
        ]);
    }

    public function store(ImportRequest $request)
    {
        Importer::import($request->validated());

        return [
            'success' => true,
            'queued' => config('queue.default') !== 'sync',
        ];
    }
}
