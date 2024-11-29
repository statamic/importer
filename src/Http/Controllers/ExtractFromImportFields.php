<?php

namespace Statamic\Importer\Http\Controllers;

trait ExtractFromImportFields
{
    protected function extractFromFields($import, $blueprint)
    {
        $fields = $blueprint
            ->fields()
            ->setParent($import)
            ->addValues($import->config()->merge([
                'name' => $import->name(),
                'file' => [basename($import->get('path'))],
            ])->all())
            ->preProcess();

        $values = $fields->values();

        return [$values->all(), $fields->meta()];
    }
}
