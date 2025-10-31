<?php

namespace Statamic\Importer\Transformers;

use Statamic\Support\Str;

class ListTransformer extends AbstractTransformer
{
    public function transform($value)
    {
        // When $value is a serialized array, deserialize it.
        if (Str::startsWith($value, 'a:')) {
            $value = collect(unserialize($value))->join('|');
        }

        // When $value is a JSON string, decode it.
        if (Str::startsWith($value, ['{', '[']) || Str::startsWith($value, ['[', ']'])) {
            $value = collect(json_decode($value, true))->join('|');
        }

        return explode('|', $value);
    }
}
