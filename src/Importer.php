<?php

namespace Statamic\Importer;

use Statamic\Importer\Imports\Import;
use Statamic\Importer\Jobs\ImportItemJob;
use Statamic\Importer\Sources\Csv;
use Statamic\Importer\Sources\Xml;

class Importer
{
    protected static array $transformers = [];

    public static function run(Import $import): void
    {
        $items = match ($import->get('type')) {
            'csv' => (new Csv($import->config()->all()))->getItems($import->get('path')),
            'xml' => (new Xml($import->config()->all()))->getItems($import->get('path')),
        };

        $items->each(fn (array $item) => ImportItemJob::dispatch($import, $item));
    }

    public static function getTransformer(string $fieldtype): ?string
    {
        return static::$transformers[$fieldtype] ?? null;
    }

    public static function registerTransformer(string $fieldtype, string $class): void
    {
        static::$transformers[$fieldtype] = $class;
    }
}
