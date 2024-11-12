<?php

namespace Statamic\Importer\Support;

use Statamic\Facades\Blink;
use Statamic\Facades\Fieldset;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Support\Str;

class FieldUpdater
{
    protected $field;
    protected $blueprint;

    public function field(Field $field): self
    {
        $this->field = $field;

        return $this;
    }

    public function blueprint(Blueprint $blueprint): self
    {
        $this->blueprint = $blueprint;

        return $this;
    }

    public function updateFieldConfig(array $config): void
    {
        if ([$fieldset, $fieldHandle] = $this->fieldImportedFrom($config)) {
            $fieldset->ensureFieldHasConfig($fieldHandle, $config)->save();

            Blink::store('blueprints.found')->flush();
            Blink::store('blueprints.from-file')->flush();

            return;
        }

        $this->blueprint->ensureFieldHasConfig(
            handle: $this->field->handle(),
            config: $config
        );

        $this->blueprint->save();
    }

    private function fieldImportedFrom(array $config): ?array
    {
        if ($prefix = $this->field->prefix()) {
            $fieldHandle = Str::after($this->field->handle(), $prefix);

            /** @var \Statamic\Fields\Fieldset $fieldset */
            $fieldset = $this->blueprint->fields()->items()
                ->filter(fn (array $field) => isset($field['import']))
                ->map(fn (array $field) => Fieldset::find($field['import']))
                ->filter(function ($fieldset) use ($prefix) {
                    return collect($fieldset->fields()->items())
                        ->where('handle', Str::after($this->field->handle(), $prefix))
                        ->isNotEmpty();
                })
                ->first();

            return [$fieldset, $fieldHandle];
        }

        $importedField = $this->blueprint->fields()->items()
            ->where('handle', $this->field->handle())
            ->filter(fn (array $field) => isset($field['field']) && is_string($field['field']))
            ->first();

        if ($importedField) {
            /** @var \Statamic\Fields\Fieldset $fieldset */
            $fieldHandle = Str::after($importedField['field'], '.');
            $fieldset = Fieldset::find(Str::before($importedField['field'], '.'));

            return [$fieldset, $fieldHandle];
        }

        return null;
    }
}
