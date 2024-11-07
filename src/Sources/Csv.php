<?php

namespace Statamic\Importer\Sources;

use Illuminate\Support\LazyCollection;
use Spatie\SimpleExcel\SimpleExcelReader;

class Csv extends AbstractSource
{
    public function getItems(string $path): LazyCollection
    {
        return SimpleExcelReader::create($path)
            ->useDelimiter($this->config('delimiter', ','))
            ->getRows();
    }

    public function fieldItems(): array
    {
        return [
            'delimiter' => [
                'display' => __('CSV Delimiter'),
                'instructions' => __('Specify the delimiter to be used when reading the CSV file. You will need to save the import for the options to be updated.'),
                'type' => 'text',
                'default' => ',',
            ],
        ];
    }
}
