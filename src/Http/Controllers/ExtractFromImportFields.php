<?php

namespace Statamic\Importer\Http\Controllers;

trait ExtractFromImportFields
{
    protected function extractFromFields($import, $fields)
    {
        $fields = $fields->preProcess();

        $values = $fields->values()->merge([
            'name' => $import->name(),
            'strategy' => array_keys($import->get('strategy')),
        ]);

        return [$values->all(), $fields->meta()];
    }
}
