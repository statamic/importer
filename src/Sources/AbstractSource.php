<?php

namespace Statamic\Importer\Sources;

use Statamic\Importer\Contracts\Source;
use Statamic\Importer\Imports\Import;

abstract class AbstractSource implements Source
{
    public function __construct(public ?Import $import = null) {}
}
