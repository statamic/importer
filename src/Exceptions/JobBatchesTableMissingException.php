<?php

namespace Statamic\Importer\Exceptions;

class JobBatchesTableMissingException extends \Exception
{
    public function __construct()
    {
        parent::__construct('The job_batches table is missing. Please run `php artisan migrate` to run the required migrations.');
    }
}
