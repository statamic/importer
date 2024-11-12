<?php

namespace Statamic\Importer\Transformers;

use Statamic\Statamic;

class ToggleTransformer extends AbstractTransformer
{
    public function transform(string $value): bool
    {
        if ($this->config('format') === 'boolean') {
            return match ($value) {
                '1', 'true' => true,
                '0', 'false' => false,
            };
        }

        if ($this->config('format') === 'string') {
            return match (true) {
                in_array($value, explode('|', $this->config('values.true'))) => true,
                in_array($value, explode('|', $this->config('values.false'))) => false,
            };
        }
    }

    public function fieldItems(): array
    {
        return [
            'format' => [
                'type' => 'select',
                'display' => __('Format'),
                'instructions' => __('importer::messages.toggle_format_instructions'),
                'options' => [
                    'boolean' => Statamic::trans('Booleans'),
                    'string' => Statamic::trans('Strings'),
                ],
                'validate' => 'required',
            ],
            'values' => [
                'type' => 'array',
                'display' => __('Values'),
                'instructions' => __('importer::messages.toggle_values_instructions'),
                'mode' => 'keyed',
                'keys' => [
                    ['key' => 'true', 'value' => __('True')],
                    ['key' => 'false', 'value' => __('False')],
                ],
                'validate' => 'required',
                'if' => [
                    'format' => 'string',
                ],
            ],
        ];
    }
}
