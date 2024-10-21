<?php

namespace Statamic\Importer\Sources;

use Illuminate\Support\Arr;
use Statamic\Importer\Contracts\Source;

abstract class AbstractSource implements Source
{
    public function __construct(public array $config) {}

    /**
     * Get the import's configuration.
     */
    protected function config(?string $key = null, $default = null): mixed
    {
        if (is_null($key)) {
            return collect($this->config);
        }

        return Arr::get($this->config, $key, $default);
    }
}
