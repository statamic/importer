<?php

namespace Statamic\Importer\Sources;

use Illuminate\Support\LazyCollection;
use Spatie\SimpleExcel\SimpleExcelReader;

class Csv extends AbstractSource
{
    public function getItems(string $path): LazyCollection
    {
        return SimpleExcelReader::create($path)->getRows()->map(function (array $row) {
            return collect($this->config('mappings'))
                ->mapWithKeys(function (array $mapping, string $fieldHandle) use ($row) {
                    return [$fieldHandle => $row[$mapping['key']]];
                })
                ->all();
        });
    }
}
