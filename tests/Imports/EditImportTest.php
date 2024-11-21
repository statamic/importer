<?php

namespace Statamic\Importer\Tests\Imports;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Collection;
use Statamic\Facades\User;
use Statamic\Importer\Facades\Import;
use Statamic\Importer\Tests\TestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class EditImportTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    public function setUp(): void
    {
        parent::setUp();

        File::deleteDirectory(storage_path('statamic/importer'));
    }

    #[Test]
    public function can_edit_an_import()
    {
        Collection::make('posts')->save();

        Storage::disk('local')->put('statamic/imports/posts/posts.csv', '');

        $import = Import::make()
            ->name('Posts')
            ->config([
                'type' => 'csv',
                'path' => Storage::disk('local')->path('statamic/imports/posts/posts.csv'),
                'destination' => ['type' => 'entries', 'collection' => 'posts'],
            ]);

        $import->save();

        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->get("/cp/utilities/importer/{$import->id()}")
            ->assertOk()
            ->assertViewHas('import');
    }
}
