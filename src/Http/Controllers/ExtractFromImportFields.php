<?php

namespace Statamic\Importer\Http\Controllers;

trait ExtractFromImportFields
{
    protected function extractFromFields($import, $blueprint)
    {
        $fields = $blueprint
            ->fields()
            ->addValues($import->config()->all())
            ->preProcess();

        $values = $fields->values()->merge([
            'name' => $import->name(),
            'file' => [basename($import->get('path'))],
        ]);

        return [$values->all(), $fields->meta()];
    }
}
