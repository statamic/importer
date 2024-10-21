<?php

namespace Statamic\Importer\Contracts;

use Illuminate\Support\LazyCollection;

interface Source
{
    public function getItems(string $path): LazyCollection;
}
