<?php

namespace Statamic\Importer\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Collection;
use Statamic\Facades\Taxonomy;

class CreateImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'type' => ['required', 'in:xml,csv'],
            'path' => ['required', function (string $attribute, mixed $value, Closure $fail) {
                if (! File::exists($value)) {
                    $fail("The file can't be found. Please ensure the path is correct.")->translate();
                }
            }],
            'destination_type' => ['required', 'in:entries,terms,users'],
            'destination_collection' => ['required_if:destination_type,entries'],
            'destination_collection.*' => [function (string $attribute, mixed $value, Closure $fail) {
                if ($value && ! Collection::find($value)) {
                    $fail('Collection could not be found.')->translate();
                }
            }],
            'destination_taxonomy' => ['required_if:destination_type,terms'],
            'destination_taxonomy.*' => [function (string $attribute, mixed $value, Closure $fail) {
                if ($value && ! Taxonomy::find($value)) {
                    $fail('Taxonomy could not be found.')->translate();
                }
            }],
        ];
    }
}
