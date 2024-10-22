<?php

namespace Statamic\Importer;

use Statamic\Importer\Jobs\ImportItemJob;
use Statamic\Importer\Sources\Csv;
use Statamic\Importer\Sources\Xml;

class Importer
{
    protected static $transformers = [];

    public static function import(array $config): void
    {
        $items = match ($config['type']) {
            'csv' => (new Csv($config))->getItems($config['path']),
            'xml' => (new Xml($config))->getItems($config['path']),
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
