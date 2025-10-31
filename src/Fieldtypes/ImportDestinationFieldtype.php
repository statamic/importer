<?php

namespace Statamic\Importer\Fieldtypes;

use Statamic\Fieldtypes\Group as GroupFieldtype;

class ImportDestinationFieldtype extends GroupFieldtype
{
    protected $selectable = false;
}