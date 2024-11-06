<?php

namespace Statamic\Importer;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Statamic\Extend\Uninstaller;
use Statamic\Importer\Facades\Import;

class Uninstall extends Uninstaller
{
    public function handle()
    {
        File::delete(Import::path());

        Storage::disk('local')->delete('statamic/imports');
    }
}
