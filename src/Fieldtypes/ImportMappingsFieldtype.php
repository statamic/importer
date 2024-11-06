<?php

namespace Statamic\Importer\Fieldtypes;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Statamic\Facades\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fields\Fieldtype;
use Statamic\Importer\Importer;
use Statamic\Importer\Sources\Csv;
use Statamic\Importer\Sources\Xml;
use Statamic\Support\Str;

class ImportMappingsFieldtype extends Fieldtype
{
    protected $selectable = false;

    public function preload()
    {
        return [
            'fields' => $this->fields()->map(function (Fields $fields, string $handle) {
                $field = $this->field()->parent()->destinationBlueprint()->field($handle);

                return [
                    'type' => $field->type(),
                    'handle' => $field->handle(),
                    'display' => $field->display(),
                    'config' => $field->config(),
                    'fieldtype_title' => $field->fieldtype()->title(),
                    'fields' => $fields->toPublishArray(),
                    'meta' => $fields->meta()->all(),
                ];
            }),
        ];
    }

    public function preProcess($data): array
    {
        return $this->fields()->map(function (Fields $fields, string $handle) use ($data) {
            $field = $this->field()->parent()->destinationBlueprint()->field($handle);

            return $fields->addValues(Arr::get($data, $field->handle(), []))->preProcess()->values()->all();
        })->all();
    }

    public function process($data): array
    {
        return $this->fields()
            ->map(function (Fields $fields, string $handle) use ($data) {
                $values = Arr::get($data, $handle);

                if (! $values || empty($values['key'])) {
                    return null;
                }

                return $fields->addValues($values)->process()->values()->all();
            })
            ->filter()
            ->all();
    }

    public function extraRules(): array
    {
        if (! $this->field->parent()) {
            return [];
        }

        $fields = $this->fields();

        return collect($this->field->value())
            ->reject(fn ($row) => empty($row['key']))
            ->flatMap(function (array $row, string $field) use ($fields) {
                $rules = $fields
                    ->get($field)
                    ->addValues($row)
                    ->validator()
                    ->rules();

                return collect($rules)->mapWithKeys(function ($rules, $handle) use ($field) {
                    return ["{$this->field->handle()}.{$field}.{$handle}" => $rules];
                })->all();
            })
            ->all();
    }

    private function fields(): Collection
    {
        $import = $this->field()->parent();

        $row = match ($import->get('type')) {
            'csv' => (new Csv($import))->getItems($import->get('path'), [])->first(),
            'xml' => (new Xml($import))->getItems($import->get('path'), [])->first(),
        };

        return $import->destinationBlueprint()->fields()->all()
            ->reject(function (Field $field) {
                $transformer = Importer::getTransformer($field->type());

                return in_array($field->type(), ['section', 'grid', 'replicator', 'group'])
                    && ! $transformer;
            })
            ->mapWithKeys(function (Field $field) use ($row) {
                $fields = [];

                if ($transformer = Importer::getTransformer($field->type())) {
                    $fields = (new $transformer(field: $field))->fieldItems();
                }

                $blueprint = Blueprint::makeFromFields([
                    'key' => [
                        'type' => 'select',
                        'hide_display' => true,
                        'options' => collect($row)->map(fn ($value, $key) => [
                            'key' => $key,
                            'value' => "<{$key}>: ".Str::truncate($value, 200),
                        ])->values(),
                        'clearable' => true,
                    ],
                    ...$fields,
                ])->setHandle('mappings-'.$field->handle());

                return [$field->handle() => $blueprint->fields()];
            });
    }
}
