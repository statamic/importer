<?php

namespace Statamic\Importer;

use Statamic\Importer\Jobs\ImportItemJob;
use Statamic\Importer\Sources\Csv;
use Statamic\Importer\Sources\Xml;

class Importer
{
    protected static $transformers = [];

    public static function import(array $config, string $path): void
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        $items = match ($extension) {
            'csv' => (new Csv($config))->getItems($path),
            'xml' => (new Xml($config))->getItems($path),
            default => throw new \Exception("Couldn't find a source for [{$extension}] files."),
        };

        $items->each(fn (array $item) => ImportItemJob::dispatch($config, $item));
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
