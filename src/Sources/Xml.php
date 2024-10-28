<?php

namespace Statamic\Importer\Sources;

use Illuminate\Support\LazyCollection;

class Xml extends AbstractSource
{
    public function getItems(string $path): LazyCollection
    {
        $xml = simplexml_load_file($path);

        return LazyCollection::make(function () use ($xml) {
            foreach ($xml->channel->item as $item) {
                $array = [];

                foreach ($item as $key => $value) {
                    $array[$key] = (string) $value;
                }

                foreach ($item->getDocNamespaces(true) as $namespace => $uri) {
                    // Access namespaced elements using the namespace prefix
                    foreach ($item->children($uri) as $key => $value) {
                        $array[$namespace.':'.$key] = (string) $value;
                    }

                    // If you want to access attributes in the namespaced elements
                    foreach ($item->attributes($uri) as $key => $value) {
                        $array[$namespace.':'.$key] = (string) $value;
                    }
                }

                // WordPress: Filter out any `attachment` post types.
                if (isset($array['wp:post_type']) && $array['wp:post_type'] === 'attachment') {
                    continue;
                }

                yield $array;
            }
        });
    }
}
