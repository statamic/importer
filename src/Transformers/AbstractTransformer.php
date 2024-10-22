<?php

namespace Statamic\Importer\Transformers;

use Illuminate\Support\Arr;
use Statamic\Extend\HasFields;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Importer\Contracts\Transformer;

abstract class AbstractTransformer implements Transformer
{
    public function __construct(protected Blueprint $blueprint, protected Field $field, protected array $config) {}

    abstract public function transform(string $value);

    /**
     * Get the field mapping configuration.
     */
    protected function config(?string $key = null, $default = null): mixed
    {
        if (is_null($key)) {
            return collect($this->config);
        }

        return Arr::get($this->config, $key, $default);
    }
}
