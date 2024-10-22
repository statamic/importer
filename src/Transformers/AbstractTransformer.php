<?php

namespace Statamic\Importer\Transformers;

use Illuminate\Support\Arr;
use Statamic\Extend\HasFields;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Importer\Contracts\Transformer;

abstract class AbstractTransformer implements Transformer
{
    use HasFields;

    public function __construct(protected ?Blueprint $blueprint = null, protected ?Field $field = null, protected ?array $config = null) {}

    abstract public function transform(string $value);

    public function fieldItems(): array
    {
        return [];
    }

    protected function config(?string $key = null, $default = null): mixed
    {
        if (is_null($key)) {
            return collect($this->config);
        }

        return Arr::get($this->config, $key, $default);
    }
}
