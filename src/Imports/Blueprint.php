<?php

namespace Statamic\Importer\Imports;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades;
use Statamic\Facades\Collection;
use Statamic\Facades\Site;

class Blueprint
{
    protected static array $allowedMimeTypes = [
        'text/csv',
        'application/csv',
        'text/plain',
        'application/xml',
        'text/xml',
    ];

    public static function getBlueprint(?Import $import = null): \Statamic\Fields\Blueprint
    {
        return Facades\Blueprint::make('import-blueprint')->setContents([
            'tabs' => [
                'main' => [
                    'sections' => [
                        [
                            'fields' => [
                                [
                                    'handle' => 'name',
                                    'field' => [
                                        'type' => 'text',
                                        'display' => __('Name'),
                                        'instructions' => __('Name this import so you can identify it later.'),
                                        'validate' => 'required',
                                    ],
                                ],
                                [
                                    'handle' => 'file',
                                    'field' => [
                                        'type' => 'files',
                                        'display' => __('Upload a new file'),
                                        'instructions' => __('Upload a CSV or XML file to import. This will replace the current file.'),
                                        'max_files' => 1,
                                        'allowed_extensions' => ['csv', 'xml'],
                                        'validate' => [
                                            'nullable',
                                            'max:1',
                                            function (string $attribute, mixed $value, Closure $fail) {
                                                if (! $value) {
                                                    return;
                                                }

                                                $path = "statamic/file-uploads/{$value[0]}";

                                                if (! Storage::disk('local')->exists($path)) {
                                                    $fail('The uploaded file could not be found.')->translate();
                                                }

                                                if (! in_array(Storage::disk('local')->mimeType($path), static::$allowedMimeTypes)) {
                                                    $fail('Only CSV and XML files can be imported at this time.')->translate();
                                                }
                                            },
                                        ],
                                    ],
                                ],
                                [
                                    'handle' => 'destination',
                                    'field' => [
                                        'type' => 'group',
                                        'hide_display' => true,
                                        'border' => false,
                                        'fullscreen' => false,
                                        'fields' => collect([
                                            [
                                                'handle' => 'type',
                                                'field' => [
                                                    'type' => 'button_group',
                                                    'display' => __('Data Type'),
                                                    'instructions' => __('Choose what type of data are you importing'),
                                                    'width' => 50,
                                                    'options' => [
                                                        ['key' => 'entries', 'value' => __('Entries')],
                                                        ['key' => 'terms', 'value' => __('Terms')],
                                                        ['key' => 'users', 'value' => __('Users')],
                                                    ],
                                                    'validate' => 'required',
                                                ],
                                            ],
                                            [
                                                'handle' => 'collection',
                                                'field' => [
                                                    'type' => 'collections',
                                                    'display' => __('Collection'),
                                                    'instructions' => __('Select the collection to import entries into.'),
                                                    'width' => 50,
                                                    'max_items' => 1,
                                                    'mode' => 'select',
                                                    'if' => ['type' => 'entries'],
                                                    'validate' => 'required_if:destination.type,entries',
                                                ],
                                            ],
                                            [
                                                'handle' => 'taxonomy',
                                                'field' => [
                                                    'type' => 'taxonomies',
                                                    'display' => __('Taxonomy'),
                                                    'instructions' => __('Select the taxonomy to import terms into.'),
                                                    'width' => 50,
                                                    'max_items' => 1,
                                                    'mode' => 'select',
                                                    'if' => ['type' => 'terms'],
                                                    'validate' => 'required_if:destination.type,terms',
                                                ],
                                            ],
                                            Site::hasMultiple() ? [
                                                'handle' => 'site',
                                                'field' => [
                                                    'type' => 'sites',
                                                    'display' => __('Site'),
                                                    'instructions' => __('Which site should the entries be imported into?'),
                                                    'width' => 50,
                                                    'max_items' => 1,
                                                    'mode' => 'select',
                                                    'if' => ['type' => 'entries'],
                                                    'validate' => [
                                                        'required_if:destination.type,entries',
                                                        function (string $attribute, mixed $value, Closure $fail) {
                                                            $collection = Collection::find(Arr::get(request()->destination, 'collection.0'));

                                                            if (count($value) && ! $collection->sites()->contains($value[0])) {
                                                                $fail('The chosen collection is not available on this site.')->translate();
                                                            }
                                                        },
                                                    ],
                                                ],
                                            ] : null,
                                        ])->filter()->all(),
                                    ],
                                ],
                                [
                                    'handle' => 'strategy',
                                    'field' => [
                                        'type' => 'checkboxes',
                                        'display' => __('Import Strategy'),
                                        'instructions' => __('Choose what should happen when importing.'),
                                        'options' => [
                                            ['key' => 'create', 'value' => __('Create new items')],
                                            ['key' => 'update', 'value' => __('Update existing items')],
                                        ],
                                        'default' => ['create', 'update'],
                                        'validate' => ['required', 'array', 'min:1'],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'display' => __('Configuration'),
                            'instructions' => __('importer::messages.configuration_instructions'),
                            'fields' => [
                                [
                                    'handle' => 'mappings',
                                    'field' => [
                                        'type' => 'import_mappings',
                                        'display' => __('Field Mappings'),
                                        'instructions' => __('importer::messages.mapping_instructions'),
                                        'validate' => [
                                            'required',
                                            'array',
                                            function (string $attribute, mixed $value, Closure $fail) {
                                                if (collect($value)->reject(fn (array $mapping) => empty($mapping['key']))->isEmpty()) {
                                                    $fail('You must map at least one field.')->translate();
                                                }
                                            },
                                        ],
                                        'if' => $import ? static::buildFieldConditions($import) : null,
                                    ],
                                ],
                                [
                                    'handle' => 'unique_field',
                                    'field' => [
                                        'type' => 'radio',
                                        'display' => __('Unique Field'),
                                        'instructions' => __('importer::messages.unique_field_instructions'),
                                        'options' => $import?->destinationBlueprint()->fields()->all()
                                            ->filter(fn ($field) => in_array($field->type(), ['text', 'integer', 'slug']))
                                            ->map(fn ($field) => ['key' => $field->handle(), 'value' => $field->display()])
                                            ->values(),
                                        'validate' => [
                                            'required',
                                            function (string $attribute, mixed $value, Closure $fail) {
                                                if (! collect(request()->mappings)->reject(fn ($mapping) => empty($mapping['key']))->has($value)) {
                                                    $fail('Please configure a mapping for this field.')->translate();
                                                }
                                            },
                                        ],
                                        'if' => $import ? static::buildFieldConditions($import) : null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    private static function buildFieldConditions(Import $import): array
    {
        $conditions = [
            'file' => 'empty',
            'destination.type' => $import->get('destination.type'),
        ];

        if ($import->get('destination.collection')) {
            $conditions['destination.collection'] = 'contains ' . $import->get('destination.collection');
        }

        if ($import->get('destination.taxonomy')) {
            $conditions['destination.taxonomy'] = 'contains ' . $import->get('destination.taxonomy');
        }

        if ($import->get('destination.site')) {
            $conditions['destination.site'] = 'contains ' . $import->get('destination.site');
        }

        return $conditions;
    }
}
