<?php

namespace Statamic\Importer\Transformers;

use Statamic\Dictionaries\Dictionary;
use Statamic\Facades;
use Statamic\Support\Str;

class DictionaryTransformer extends AbstractTransformer
{
    public function transform(string $value): null|string|array
    {
        // When $value is a JSON string, decode it.
        if (Str::startsWith($value, ['{', '[']) || Str::startsWith($value, ['[', ']'])) {
            $value = collect(json_decode($value, true))->join('|');
        }

        $options = collect(explode('|', $value))->map(function ($value) {
            return $this->dictionary()->get($value)?->value();
        })->filter()->values();

        return $this->field->get('max_items') === 1 ? $options->first() : $options->all();
    }

    private function dictionary(): Dictionary
    {
        $dictionary = $this->field->get('dictionary');

        if (is_string($dictionary)) {
            return Facades\Dictionary::find($dictionary);
        }

        return Facades\Dictionary::find($dictionary['type'], $dictionary);
    }
}
