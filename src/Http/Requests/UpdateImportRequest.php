<?php

namespace Statamic\Importer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'run' => ['nullable', 'boolean'],
            'mappings' => ['required', 'array', function ($attribute, $value, $fail) {
                if (collect($value)->reject(fn (array $mapping) => empty($mapping['key']))->isEmpty()) {
                    $fail('You must map at least one field.')->translate();
                }
            }],
            'mappings.*.key' => ['nullable', 'string'],
            'unique_field' => ['required', 'string'],
        ];
    }
}
