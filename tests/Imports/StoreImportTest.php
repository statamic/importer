<?php

namespace Imports;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Collection;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\User;
use Statamic\Importer\Facades\Import;
use Statamic\Importer\Tests\TestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class StoreImportTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    public function setUp(): void
    {
        parent::setUp();

        File::deleteDirectory(storage_path('statamic/importer'));
        Storage::disk('local')->deleteDirectory('statamic/file-uploads');
    }

    #[Test]
    public function it_stores_a_collection_import()
    {
        Collection::make('posts')->save();

        // The Files fieldtype will upload this before the form gets submitted.
        Storage::disk('local')->put('statamic/file-uploads/123456789/import.csv', '');

        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->post('/cp/utilities/importer', [
                'name' => 'Posts',
                'file' => [
                    '123456789/import.csv',
                ],
                'destination_type' => 'entries',
                'destination_collection' => ['posts'],
            ])
            ->assertJsonStructure(['redirect']);

        $import = Import::find('posts');

        $this->assertNotNull($import);
        $this->assertEquals('Posts', $import->name());
        $this->assertEquals('csv', $import->get('type'));
        $this->assertEquals(storage_path('app/statamic/imports/posts.csv'), $import->get('path'));
    }

    #[Test]
    public function it_stores_a_taxonomy_import()
    {
        Taxonomy::make('categories')->save();

        // The Files fieldtype will upload this before the form gets submitted.
        Storage::disk('local')->put('statamic/file-uploads/123456789/import.csv', '');

        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->post('/cp/utilities/importer', [
                'name' => 'Categories',
                'file' => [
                    '123456789/import.csv',
                ],
                'destination_type' => 'terms',
                'destination_taxonomy' => ['categories'],
            ])
            ->assertJsonStructure(['redirect']);

        $import = Import::find('categories');

        $this->assertNotNull($import);
        $this->assertEquals('Categories', $import->name());
        $this->assertEquals('csv', $import->get('type'));
        $this->assertEquals(storage_path('app/statamic/imports/categories.csv'), $import->get('path'));
    }

    #[Test]
    public function it_stores_a_user_import()
    {
        // The Files fieldtype will upload this before the form gets submitted.
        Storage::disk('local')->put('statamic/file-uploads/123456789/import.csv', '');

        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->post('/cp/utilities/importer', [
                'name' => 'Users',
                'file' => [
                    '123456789/import.csv',
                ],
                'destination_type' => 'users',
            ])
            ->assertJsonStructure(['redirect']);

        $import = Import::find('users');

        $this->assertNotNull($import);
        $this->assertEquals('Users', $import->name());
        $this->assertEquals('csv', $import->get('type'));
        $this->assertEquals(storage_path('app/statamic/imports/users.csv'), $import->get('path'));
    }

    #[Test]
    public function validation_error_is_thrown_when_file_is_missing()
    {
        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->post('/cp/utilities/importer', [
                'name' => 'Foo',
                'file' => [
                    '123456789/import.csv',
                ],
                'destination_type' => 'users',
            ])
            ->assertSessionHasErrors('file.0');

        $this->assertNull(Import::find('foo'));
    }

    #[Test]
    public function validation_error_is_thrown_when_file_mime_type_is_not_allowed()
    {
        // The Files fieldtype will upload this before the form gets submitted.
        Storage::disk('local')->put('statamic/file-uploads/123456789/import.pdf', '');

        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->post('/cp/utilities/importer', [
                'name' => 'Foo',
                'file' => [
                    '123456789/import.pdf',
                ],
                'destination_type' => 'users',
            ])
            ->assertSessionHasErrors('file.0');

        $this->assertNull(Import::find('foo'));
    }

    #[Test]
    public function validation_error_is_thrown_when_destination_type_is_invalid()
    {
        // The Files fieldtype will upload this before the form gets submitted.
        Storage::disk('local')->put('statamic/file-uploads/123456789/import.csv', '');

        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->post('/cp/utilities/importer', [
                'name' => 'Foo',
                'file' => [
                    '123456789/import.csv',
                ],
                'destination_type' => null,
            ])
            ->assertSessionHasErrors('destination_type');

        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->post('/cp/utilities/importer', [
                'name' => 'Foo',
                'file' => [
                    '123456789/import.csv',
                ],
                'destination_type' => 'globals',
            ])
            ->assertSessionHasErrors('destination_type');

        $this->assertNull(Import::find('foo'));
    }
}
