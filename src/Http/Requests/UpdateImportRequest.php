<?php

namespace Statamic\Importer\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Collection;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\User;
use Statamic\Importer\Importer;

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
            'mappings' => ['required', 'array'],
            'mappings.*.key' => ['nullable', 'string'],
            'unique_key' => ['required', 'string'],
        ];
    }
}
