<?php

namespace Imports;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Importer\Facades\Import;
use Statamic\Importer\Tests\TestCase;

class ImportTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        File::deleteDirectory(storage_path('statamic/importer'));
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
}
