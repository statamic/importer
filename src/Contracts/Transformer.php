<?php

namespace Statamic\Importer\Contracts;

interface Transformer
{
    public function transform(string $value);
}
