<?php

namespace Statamic\Importer\Http\Controllers;

trait ExtractFromImportFields
{
    protected function extractFromFields($import, $fields)
    {
        $fields = $fields->preProcess();

        $values = $fields->values();

        return [$values->all(), $fields->meta()];
    }
}
