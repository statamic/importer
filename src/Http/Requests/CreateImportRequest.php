<?php

namespace Statamic\Importer\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\Collection;
use Statamic\Facades\Taxonomy;

class CreateImportRequest extends FormRequest
{
    protected array $allowedMimeTypes = [
        'text/csv',
        'application/csv',
        'text/plain',
        'application/xml',
        'text/xml',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'file' => ['required', 'array'],
            'file.0' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) {
                $path = "statamic/file-uploads/{$value}";

                if (! Storage::disk('local')->exists($path)) {
                    $fail('The uploaded file could not be found.')->translate();
                }

                if (! in_array(Storage::disk('local')->mimeType($path), $this->allowedMimeTypes)) {
                    $fail('Only CSV and XML files can be imported at this time.')->translate();
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
