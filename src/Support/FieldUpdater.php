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
        if ($prefix = $this->field->prefix()) {
            $this->updatePrefixedField($prefix, $config);
            return;
        }

        if ($importedField = $this->getImportedField()) {
            $this->updateImportedField($importedField, $config);
            return;
        }

        $this->blueprint->ensureFieldHasConfig(
            handle: $this->field->handle(),
            config: $config
        );

        $this->blueprint->save();
    }

    private function getImportedField(): ?array
    {
        return $this->blueprint->fields()->items()
            ->where('handle', $this->field->handle())
            ->filter(fn (array $field) => isset($field['field']) && is_string($field['field']))
            ->first();
    }

    /**
     * This method handles updating imported fields from fieldsets.
     *
     * -
     *   handle: foo
     *   field: fieldset.foo
     */
    private function updateImportedField(array $importedField, array $config): void
    {
        /** @var \Statamic\Fields\Fieldset $fieldset */
        $fieldHandle = Str::after($importedField['field'], '.');
        $fieldset = Fieldset::find(Str::before($importedField['field'], '.'));

        $fieldset->setContents([
            ...$fieldset->contents(),
            'fields' => collect($fieldset->contents()['fields'])
                ->map(function (array $field) use ($config, $fieldHandle) {
                    if ($field['handle'] === $fieldHandle) {
                        return [
                            'handle' => $field['handle'],
                            'field' => $config,
                        ];
                    }

                    return $field;
                })
                ->all(),
        ]);

        $fieldset->save();

        $this->clearBlinkCaches();
    }

    /**
     * This method handles updating imported fields from fieldsets, which use a prefix.
     *
     * -
     *   import: fieldset
     *   prefix: foo_
     */
    private function updatePrefixedField(string $prefix, array $config): void
    {
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

        $fieldset->setContents([
            ...$fieldset->contents(),
            'fields' => collect($fieldset->contents()['fields'])
                ->map(function (array $field) use ($config, $prefix) {
                    if ($field['handle'] === Str::after($this->field->handle(), $prefix)) {
                        return [
                            'handle' => $field['handle'],
                            'field' => $config,
                        ];
                    }

                    return $field;
                })
                ->all(),
        ]);

        $fieldset->save();

        $this->clearBlinkCaches();
    }

    /**
     * When fieldsets are updated, we need to clear the Blueprint Blink caches, so
     * Blueprint::find() returns the updated field config.
     */
    private function clearBlinkCaches(): void
    {
        Blink::store('blueprints.found')->flush();
        Blink::store('blueprints.from-file')->flush();
    }
}
