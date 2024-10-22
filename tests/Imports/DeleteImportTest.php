<?php

namespace Imports;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Collection;
use Statamic\Facades\User;
use Statamic\Importer\Facades\Import;
use Statamic\Importer\Tests\TestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class DeleteImportTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    public function setUp(): void
    {
        parent::setUp();

        File::deleteDirectory(storage_path('statamic/importer'));
    }

    #[Test]
    public function it_deletes_an_import()
    {
        Collection::make('posts')->save();

        $import = Import::make()
            ->name('Posts')
            ->config(['type' => 'csv', 'path' => 'posts.csv', 'destination' => ['type' => 'entries', 'collection' => 'posts']]);

        $import->save();

        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->delete("/cp/utilities/importer/{$import->id()}")
            ->assertOk();

        $this->assertNull(Import::find($import->id()));
    }
}
