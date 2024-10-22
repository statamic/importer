<?php

namespace Statamic\Importer\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Collection;
use Statamic\Facades\Taxonomy;

class MappingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:xml,csv'],
            'path' => ['required', function (string $attribute, mixed $value, Closure $fail) {
                if (! File::exists($value)) {
                    $fail('The path does not exist.')->translate();
                }
            }],
            'destination' => ['required', 'array'],
            'destination.type' => ['required', 'in:entries,terms,users'],
            'destination.collection' => ['required_if:destination.type,entries', function (string $attribute, mixed $value, Closure $fail) {
                if ($value && ! Collection::find($value)) {
                    $fail('The collection does not exist.')->translate();
                }
            }],
            'destination.taxonomy' => ['required_if:destination.type,terms', function (string $attribute, mixed $value, Closure $fail) {
                if ($value && ! Taxonomy::find($value)) {
                    $fail('The taxonomy does not exist.')->translate();
                }
            }],
        ];
    }
}
