<?php

namespace Statamic\Importer\Imports;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades;
use Statamic\Facades\Collection;
use Statamic\Facades\Site;
use Statamic\Importer\Sources\Csv;
use Statamic\Importer\Sources\Xml;

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
                                        'instructions' => __('importer::messages.import_name_instructions'),
                                        'validate' => 'required',
                                    ],
                                ],
                                [
                                    'handle' => 'file',
                                    'field' => [
                                        'type' => 'files',
                                        'display' => __('File'),
                                        'instructions' => __('importer::messages.import_file_instructions'),
                                        'max_files' => 1,
                                        'allowed_extensions' => ['csv', 'xml'],
                                        'validate' => [
                                            'required',
                                            'max:1',
                                            function (string $attribute, mixed $value, Closure $fail) use ($import) {
                                                if (! $value) {
                                                    return;
                                                }

                                                if ($value[0] === basename($import?->get('path'))) {
                                                    return;
                                                }

                                                $path = "statamic/file-uploads/{$value[0]}";

                                                if (! Storage::disk('local')->exists($path)) {
                                                    $fail('importer::validation.file_type_not_allowed')->translate();
                                                }

                                                if (! in_array(Storage::disk('local')->mimeType($path), static::$allowedMimeTypes)) {
                                                    $fail('importer::validation.uploaded_file_not_found')->translate();
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
                                                    'instructions' => __('importer::messages.destination_type_instructions'),
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
                                                    'instructions' => __('importer::messages.destination_collection_instructions'),
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
                                                    'instructions' => __('importer::messages.destination_taxonomy_instructions'),
                                                    'width' => 50,
                                                    'max_items' => 1,
                                                    'mode' => 'select',
                                                    'if' => ['type' => 'terms'],
                                                    'validate' => 'required_if:destination.type,terms',
                                                ],
                                            ],
                                            // todo: think about a way to make this only show when the collection/taxonomy has more than one blueprint
                                            [
                                                'handle' => 'blueprint',
                                                'field' => [
                                                    'type' => 'blueprint',
                                                    'display' => __('Blueprint'),
                                                    'width' => 50,
                                                    'unless' => ['destination.type' => 'users'],
                                                ],
                                            ],
                                            Site::hasMultiple() ? [
                                                'handle' => 'site',
                                                'field' => [
                                                    'type' => 'sites',
                                                    'display' => __('Site'),
                                                    'instructions' => __('importer::messages.destination_site_instructions'),
                                                    'width' => 50,
                                                    'max_items' => 1,
                                                    'mode' => 'select',
                                                    'if' => ['type' => 'entries'],
                                                    'validate' => [
                                                        'required_if:destination.type,entries',
                                                        function (string $attribute, mixed $value, Closure $fail) {
                                                            $collection = Collection::find(Arr::get(request()->destination, 'collection.0'));

                                                            if (count($value) && ! $collection->sites()->contains($value[0])) {
                                                                $fail('importer::validation.site_not_configured_in_collection')->translate();
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
                                        'instructions' => __('importer::messages.strategy_instructions'),
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
                                ...static::getSourceFields($import),
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
                                                    $fail('importer::validation.mappings_not_provided')->translate();
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
                                                    $fail('importer::validation.unique_field_without_mapping')->translate();
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

    private static function getSourceFields(?Import $import = null): array
    {
        if (! $import) {
            return [];
        }

        $fields = match ($import->get('type')) {
            'csv' => (new Csv($import))->fields(),
            'xml' => (new Xml($import))->fields(),
        };

        if ($fields->items()->isEmpty()) {
            return [];
        }

        return [[
            'handle' => 'source',
            'field' => [
                'type' => 'group',
                'hide_display' => true,
                'fullscreen' => false,
                'border' => false,
                'fields' => $fields->items()->all(),
            ],
        ]];
    }

    private static function buildFieldConditions(Import $import): array
    {
        $conditions = [
            'file' => 'contains '.basename($import->get('path')),
            'destination.type' => $import->get('destination.type'),
        ];

        if ($import->get('destination.collection')) {
            $conditions['destination.collection'] = 'contains '.$import->get('destination.collection');
        }

        if ($import->get('destination.taxonomy')) {
            $conditions['destination.taxonomy'] = 'contains '.$import->get('destination.taxonomy');
        }

        if ($import->get('destination.site')) {
            $conditions['destination.site'] = 'contains '.$import->get('destination.site');
        }

        return $conditions;
    }
}
