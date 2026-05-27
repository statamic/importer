<?php

namespace Statamic\Importer\Facades;

use Illuminate\Support\Facades\Facade;
use Statamic\Importer\Imports\ImportRepository;

/**
 * @see \Statamic\Importer\Imports\ImportRepository
 */
class Import extends Facade
{
    protected static function getFacadeAccessor()
    {
        return ImportRepository::class;
    }
}
