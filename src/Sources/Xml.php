<?php

namespace Statamic\Importer\Sources;

use Illuminate\Support\Arr;
use Illuminate\Support\LazyCollection;

class Xml extends AbstractSource
{
    public function getItems(string $path): LazyCollection
    {
        $xml = simplexml_load_file($path);

        return LazyCollection::make(function () use ($xml) {
            foreach ($xml->channel->item as $item) {
                yield collect($this->config('mappings'))
                    ->mapWithKeys(function (array $mapping, string $fieldHandle) use ($item) {
                        $value = (string) Arr::first($item->xpath($mapping['key']));

                        return [$fieldHandle => $value];
                    })
                    ->all();
            }
        });
    }
}
