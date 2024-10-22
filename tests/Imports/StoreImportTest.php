<?php

namespace Imports;

use Illuminate\Support\Facades\File;
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
    }

    #[Test]
    public function it_stores_a_collection_import()
    {
        Collection::make('posts')->save();

        File::put($path = storage_path('import.csv'), '');

        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->post('/cp/utilities/importer', [
                'name' => 'Posts',
                'type' => 'csv',
                'path' => $path,
                'destination_type' => 'entries',
                'destination_collection' => ['posts'],
            ])
            ->assertJsonStructure(['redirect']);

        $this->assertNotNull(Import::find('posts'));
    }

    #[Test]
    public function it_stores_a_taxonomy_import()
    {
        Taxonomy::make('categories')->save();

        File::put($path = storage_path('import.csv'), '');

        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->post('/cp/utilities/importer', [
                'name' => 'Categories',
                'type' => 'csv',
                'path' => $path,
                'destination_type' => 'terms',
                'destination_taxonomy' => ['categories'],
            ])
            ->assertJsonStructure(['redirect']);

        $this->assertNotNull(Import::find('categories'));
    }

    #[Test]
    public function it_stores_a_user_import()
    {
        File::put($path = storage_path('import.csv'), '');

        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->post('/cp/utilities/importer', [
                'name' => 'Users',
                'type' => 'csv',
                'path' => $path,
                'destination_type' => 'users',
            ])
            ->assertJsonStructure(['redirect']);

        $this->assertNotNull(Import::find('users'));
    }

    #[Test]
    public function validation_error_is_thrown_when_path_is_invalid()
    {
        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->post('/cp/utilities/importer', [
                'name' => 'Foo',
                'type' => 'csv',
                'path' => '/path/to/nowhere.csv',
                'destination_type' => 'users',
            ])
            ->assertSessionHasErrors('path');

        $this->assertNull(Import::find('foo'));
    }

    #[Test]
    public function validation_error_is_thrown_when_type_is_invalid()
    {
        File::put($path = storage_path('import.csv'), '');

        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->post('/cp/utilities/importer', [
                'name' => 'Foo',
                'type' => null,
                'path' => $path,
                'destination_type' => 'users',
            ])
            ->assertSessionHasErrors('type');

        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->post('/cp/utilities/importer', [
                'name' => 'Foo',
                'type' => 'pdf',
                'path' => $path,
                'destination_type' => 'users',
            ])
            ->assertSessionHasErrors('type');

        $this->assertNull(Import::find('foo'));
    }

    #[Test]
    public function validation_error_is_thrown_when_destination_type_is_invalid()
    {
        File::put($path = storage_path('import.csv'), '');

        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->post('/cp/utilities/importer', [
                'name' => 'Foo',
                'type' => 'csv',
                'path' => $path,
                'destination_type' => null,
            ])
            ->assertSessionHasErrors('destination_type');

        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->post('/cp/utilities/importer', [
                'name' => 'Foo',
                'type' => 'csv',
                'path' => $path,
                'destination_type' => 'globals',
            ])
            ->assertSessionHasErrors('destination_type');

        $this->assertNull(Import::find('foo'));
    }
}
