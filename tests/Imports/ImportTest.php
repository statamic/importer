<?php

namespace Statamic\Importer\Tests\Imports;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Importer\Facades\Import;
use Statamic\Importer\Tests\TestCase;

class ImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        File::deleteDirectory(storage_path('statamic/importer'));

        Storage::disk('local')->deleteDirectory('statamic/imports');
    }

    #[Test]
    public function can_get_all_imports()
    {
        Import::make()->name('Posts')->config(['type' => 'csv', 'path' => 'posts.csv'])->save();
        Import::make()->name('Pages')->config(['type' => 'csv', 'path' => 'pages.csv'])->save();

        $all = Import::all();

        $this->assertCount(2, $all);
    }

    #[Test]
    public function can_find_import()
    {
        Import::make()->id('posts')->name('Posts')->config(['type' => 'csv', 'path' => 'posts.csv'])->save();

        $find = Import::find('posts');

        $this->assertInstanceOf(\Statamic\Importer\Imports\Import::class, $find);
        $this->assertEquals('Posts', $find->name());
    }

    #[Test]
    public function can_save_import()
    {
        $import = Import::make()->name('Posts')->config(['type' => 'csv', 'path' => 'posts.csv']);

        $this->assertNull($import->id());

        $import->save();

        $this->assertNotNull($import->id());
        $this->assertFileExists($import->path());
    }

    #[Test]
    public function can_delete_import()
    {
        $import = Import::make()->name('Posts')->config(['type' => 'csv', 'path' => 'posts.csv']);
        $import->save();

        $this->assertFileExists($import->path());

        $import->delete();

        $this->assertFileDoesNotExist($import->path());
    }

    #[Test]
    public function get_local_file_path_resolves_a_relative_path_via_the_local_disk()
    {
        Storage::disk('local')->put('statamic/imports/posts/import.csv', '');

        $import = Import::make()->name('Posts')->config(['type' => 'csv', 'path' => 'statamic/imports/posts/import.csv']);

        $this->assertEquals(
            Storage::disk('local')->path('statamic/imports/posts/import.csv'),
            $import->getLocalFilePath()
        );
    }

    #[Test]
    public function get_local_file_path_returns_a_legacy_absolute_path_as_is()
    {
        $absolutePath = Storage::disk('local')->path('statamic/imports/posts/import.csv');

        $import = Import::make()->name('Posts')->config(['type' => 'csv', 'path' => $absolutePath]);

        $this->assertEquals($absolutePath, $import->getLocalFilePath());
    }

    #[Test]
    public function can_delete_file_with_a_relative_path()
    {
        Storage::disk('local')->put('statamic/imports/posts/import.csv', '');

        $import = Import::make()->name('Posts')->config(['type' => 'csv', 'path' => 'statamic/imports/posts/import.csv']);

        Storage::disk('local')->assertExists('statamic/imports/posts/import.csv');

        $import->deleteFile();

        Storage::disk('local')->assertMissing('statamic/imports/posts/import.csv');
    }

    #[Test]
    public function can_delete_file_with_a_legacy_absolute_path()
    {
        $absolutePath = Storage::disk('local')->path('statamic/imports/posts/import.csv');
        Storage::disk('local')->put('statamic/imports/posts/import.csv', '');

        $import = Import::make()->name('Posts')->config(['type' => 'csv', 'path' => $absolutePath]);

        $this->assertFileExists($absolutePath);

        $import->deleteFile();

        $this->assertFileDoesNotExist($absolutePath);
    }
}
