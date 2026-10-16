<?php

namespace Statamic\Importer\Support;

use Statamic\Facades\Blink;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
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

    public function blueprint(Blueprint $blueprint): self
    {
        $this->blueprint = $blueprint;

        return $this;
    }

    public function updateFieldConfig(array $config): void
    {
        $this->updateFieldConfigIn($this->blueprint, $this->field->handle(), $config);
    }

    private function updateFieldConfigIn(Blueprint|Fieldset $container, string $handle, array $config): void
    {
        $field = $container->fields()->items()->firstWhere('handle', $handle);

        if ($field && is_string($field['field'])) {
            $this->updateFieldConfigIn(
                container: Fieldset::find(Str::before($field['field'], '.')),
                handle: Str::after($field['field'], '.'),
                config: $config
            );

            return;
        }

        if ($field) {
            $container->ensureFieldHasConfig($handle, $config)->save();

            $this->forgetCachedBlueprints();

            return;
        }

        $import = $container->fields()->items()
            ->filter(fn (array $field) => isset($field['import']))
            ->first(fn (array $field) => (new Fields([$field]))->all()->has($handle));

        if (! $import) {
            return;
        }

        $this->updateFieldConfigIn(
            container: Fieldset::find($import['import']),
            handle: Str::after($handle, $import['prefix'] ?? ''),
            config: $config
        );
    }

    private function forgetCachedBlueprints(): void
    {
        Blink::store('blueprints.found')->flush();
        Blink::store('blueprints.from-file')->flush();
    }
}
