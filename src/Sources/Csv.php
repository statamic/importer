<?php

namespace Statamic\Importer\Sources;

use Illuminate\Support\LazyCollection;
use Spatie\SimpleExcel\SimpleExcelReader;

class Csv extends AbstractSource
{
    public function getItems(string $path): LazyCollection
    {
        return SimpleExcelReader::create($path)
            ->useDelimiter($this->config('csv_delimiter', ','))
            ->getRows();
    }

    public function fieldItems(): array
    {
        return [
            'csv_delimiter' => [
                'display' => __('CSV Delimiter'),
                'instructions' => __('importer::messages.csv_delimiter_instructions'),
                'type' => 'text',
                'default' => ',',
            ],
        ];
    }
}
