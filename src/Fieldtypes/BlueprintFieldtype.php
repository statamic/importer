<?php

namespace Statamic\Importer\Fieldtypes;

use Statamic\Facades\Collection;
use Statamic\Facades\Taxonomy;
use Statamic\Fields\Fieldtype;

class BlueprintFieldtype extends Fieldtype
{
    protected $selectable = false;
    protected static $handle = 'import_blueprint';

    public function preload()
    {
        return [
            'collectionBlueprints' => Collection::all()->mapWithKeys(function ($collection) {
                return [$collection->handle() => $collection->entryBlueprints()->values()];
            })->all(),
            'taxonomyBlueprints' => Taxonomy::all()->mapWithKeys(function ($taxonomy) {
                return [$taxonomy->handle() => $taxonomy->termBlueprints()->values()];
            })->all(),
        ];
    }
}
