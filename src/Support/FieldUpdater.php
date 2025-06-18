<?php

namespace Statamic\Importer\Support;

use Statamic\Facades\Blink;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fieldset;
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

    public function blueprint(Blueprint|Fieldset $blueprint): self
    {
        $this->blueprint = $blueprint;

        return $this;
    }

    public function updateFieldConfig(array $config): void
    {
        if ($linkedField = $this->getLinkedField()) {
            $this->updateLinkedField($linkedField, $config);

            return;
        }

        if ($this->isImportedField()) {
            $this->updateImportedField($config, $this->field->prefix());

            return;
        }

        $this->blueprint->ensureFieldHasConfig(
            handle: $this->field->handle(),
            config: $config
        );

        $this->blueprint->save();
    }

    private function getLinkedField(): ?array
    {
        return $this->blueprint->fields()->items()
            ->where('handle', $this->field->handle())
            ->filter(fn (array $field) => isset($field['field']) && is_string($field['field']))
            ->first();
    }

    /**
     * This method handles updating linked fields from fieldsets.
     *
     * -
     *   handle: foo
     *   field: fieldset.foo
     */
    private function updateLinkedField(array $importedField, array $config): void
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
     * Determines if a field is imported from a fieldset by checking if it exists in the blueprint's top-level fields.
     */
    private function isImportedField(): bool
    {
        $topLevelFieldHandles = $this->blueprint->fields()->items()->pluck('handle')->filter();

        return $this->blueprint->field($this->field->handle()) && ! $topLevelFieldHandles->contains($this->field->handle());
    }

    /**
     * This method handles updating imported fields from fieldsets, either with or without prefixes.
     *
     * -
     *   import: fieldset
     *   prefix: foo_
     */
    private function updateImportedField(array $config, ?string $prefix = null): void
    {
        /** @var \Statamic\Fields\Fieldset $fieldset */
        $fieldset = $this->blueprint->fields()->items()
            ->filter(fn (array $field) => isset($field['import']))
            ->mapWithKeys(fn (array $field) => [$field['prefix'] ?? '' => Fieldset::find($field['import'])])
            ->filter(function ($fieldset, $prefix) use ($config) {
                // When the field exists in the fieldset, but it's not a top-level field,
                // pass the Fieldset to the FieldUpdater (this class) to update the field config.
                $fieldHandleWithoutBlueprintPrefix = Str::after($this->field->handle(), $prefix);

                if (
                    ($field = $fieldset->field($fieldHandleWithoutBlueprintPrefix))
                    && ! $fieldset->fields()->items()->pluck('handle')->filter()->contains($fieldHandleWithoutBlueprintPrefix)
                ) {
                    (new self)
                        ->field($field)
                        ->blueprint($fieldset)
                        ->updateFieldConfig($config);

                    return false;
                }

                return collect($fieldset->fields()->items())
                    ->where('handle', Str::after($this->field->handle(), $prefix))
                    ->isNotEmpty();
            })
            ->first();

        if (! $fieldset) {
            return;
        }

        $fieldset->setContents([
            ...$fieldset->contents(),
            'fields' => collect($fieldset->contents()['fields'])
                ->map(function (array $field) use ($config, $prefix) {
                    if ($field['handle'] === Str::after($this->field->handle(), $prefix ?? '')) {
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
