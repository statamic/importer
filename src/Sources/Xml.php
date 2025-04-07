<?php

namespace Statamic\Importer\Sources;

use Illuminate\Support\LazyCollection;

class Xml extends AbstractSource
{
    public function getItems(string $path): LazyCollection
    {
        return LazyCollection::make(function () use ($path) {
            $reader = new \XMLReader;
            $reader->open($path);

            while ($reader->read()) {
                if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'item') {
                    $node = $reader->expand();
                    $array = [];

                    $doc = new \DOMDocument;
                    $node = $doc->importNode($node, true);
                    $doc->appendChild($node);
                    $item = simplexml_import_dom($doc);

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

                    if (isset($array['wp:post_type']) && $array['wp:post_type'] === 'attachment') {
                        continue;
                    }

                    yield $array;
                }
            }

            $reader->close();
        });
    }
}
