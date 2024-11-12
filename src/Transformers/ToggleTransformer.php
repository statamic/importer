<?php

namespace Statamic\Importer\Transformers;

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
                'instructions' => __('How is the value stored?'),
                'options' => [
                    'boolean' => __('Booleans'),
                    'string' => __('Strings'),
                ],
                'validate' => 'required',
            ],
            'values' => [
                'type' => 'array',
                'display' => __('Values'),
                'instructions' => __('Specify the values that represent true and false in your data. You may separate multiple values with a pipe (`|`).'),
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
