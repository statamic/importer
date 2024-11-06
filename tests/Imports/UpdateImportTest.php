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

class UpdateImportTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    protected $import;

    public function setUp(): void
    {
        parent::setUp();

        File::deleteDirectory(storage_path('statamic/importer'));

        Storage::disk('local')->deleteDirectory('statamic/imports');
        Storage::disk('local')->deleteDirectory('statamic/file-uploads');

        $collection = tap(Collection::make('posts'))->save();

        $collection->entryBlueprint()->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        ['handle' => 'title', 'field' => ['type' => 'text']],
                        ['handle' => 'slug', 'field' => ['type' => 'slug']],
                        ['handle' => 'content', 'field' => ['type' => 'textarea']],
                        ['handle' => 'author', 'field' => ['type' => 'users', 'max_items' => 1]],
                        ['handle' => 'foo', 'field' => ['type' => 'text']],
                    ],
                ],
            ],
        ])->save();

        Storage::disk('local')->put('statamic/imports/posts/posts.csv', '');

        $this->import = Import::make()
            ->name('Posts')
            ->config([
                'type' => 'csv',
                'path' => Storage::disk('local')->path('statamic/imports/posts/posts.csv'),
                'destination' => ['type' => 'entries', 'collection' => 'posts'],
            ]);

        $this->import->save();
    }

    #[Test]
    public function can_update_an_import()
    {
        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->patch("/cp/utilities/importer/{$this->import->id()}", [
                'name' => 'Old Posts',
                'destination' => ['type' => 'entries', 'collection' => ['posts']],
                'strategy' => ['create', 'update'],
                'mappings' => [
                    'title' => ['key' => 'Title'],
                    'slug' => ['key' => 'Slug'],
                    'content' => ['key' => 'Content'],
                    'author' => ['key' => 'Author Email', 'related_field' => 'email'],
                    'foo' => ['key' => null],
                ],
                'unique_field' => 'slug',
            ])
            ->assertOk();

        $import = $this->import->fresh();

        $this->assertEquals('Old Posts', $import->name());
        $this->assertEquals($this->import->get('path'), $import->get('path'));
        $this->assertCount(4, $import->get('mappings'));
    }

    #[Test]
    public function can_replace_the_file()
    {
        // The Files fieldtype will upload this before the form gets submitted.
        Storage::disk('local')->put('statamic/file-uploads/123456789/latest-posts.csv', '');

        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->patch("/cp/utilities/importer/{$this->import->id()}", [
                'name' => 'Posts',
                'file' => ['123456789/latest-posts.csv'],
                'destination' => ['type' => 'entries', 'collection' => ['posts']],
                'strategy' => ['create', 'update'],
                'mappings' => [
                    'title' => ['key' => 'Title'],
                    'slug' => ['key' => 'Slug'],
                    'content' => ['key' => 'Content'],
                    'author' => ['key' => 'Author Email', 'related_field' => 'email'],
                    'foo' => ['key' => null],
                ],
                'unique_field' => 'slug',
            ])
            ->assertOk();

        $import = $this->import->fresh();

        $this->assertEquals('latest-posts.csv', basename($import->get('path')));

        Storage::disk('local')->assertExists('statamic/imports/posts/latest-posts.csv');
    }

    #[Test]
    public function validation_error_is_thrown_when_file_does_not_exist()
    {
        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->patch("/cp/utilities/importer/{$this->import->id()}", [
                'name' => 'Posts',
                'file' => ['123456789/latest-posts.pdf'],
                'destination' => ['type' => 'entries', 'collection' => ['posts']],
                'strategy' => ['create', 'update'],
                'mappings' => [
                    'title' => ['key' => 'Title'],
                    'slug' => ['key' => 'Slug'],
                    'content' => ['key' => 'Content'],
                    'author' => ['key' => 'Author Email', 'related_field' => 'email'],
                    'foo' => ['key' => null],
                ],
                'unique_field' => 'slug',
            ])
            ->assertSessionHasErrors('file');

        $import = $this->import->fresh();

        $this->assertNotEquals('latest-posts.csv', basename($import->get('path')));

        Storage::disk('local')->assertMissing('statamic/imports/posts/latest-posts.csv');
    }

    #[Test]
    public function validation_error_is_thrown_when_file_mime_type_is_not_allowed()
    {
        // The Files fieldtype will upload this before the form gets submitted.
        Storage::disk('local')->put('statamic/file-uploads/123456789/latest-posts.pdf', '');

        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->patch("/cp/utilities/importer/{$this->import->id()}", [
                'name' => 'Posts',
                'file' => ['123456789/latest-posts.pdf'],
                'destination' => ['type' => 'entries', 'collection' => ['posts']],
                'strategy' => ['create', 'update'],
                'mappings' => [
                    'title' => ['key' => 'Title'],
                    'slug' => ['key' => 'Slug'],
                    'content' => ['key' => 'Content'],
                    'author' => ['key' => 'Author Email', 'related_field' => 'email'],
                    'foo' => ['key' => null],
                ],
                'unique_field' => 'slug',
            ])
            ->assertSessionHasErrors('file');

        $import = $this->import->fresh();

        $this->assertNotEquals('latest-posts.csv', basename($import->get('path')));

        Storage::disk('local')->assertMissing('statamic/imports/posts/latest-posts.csv');
    }

    #[Test]
    public function validation_error_is_thrown_without_an_import_strategy()
    {
        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->patch("/cp/utilities/importer/{$this->import->id()}", [
                'name' => 'Posts',
                'destination' => ['type' => 'entries', 'collection' => ['posts']],
                'strategy' => [],
                'mappings' => [
                    'title' => ['key' => 'Title'],
                    'slug' => ['key' => 'Slug'],
                    'content' => ['key' => 'Content'],
                    'author' => ['key' => 'Author Email', 'related_field' => 'email'],
                    'foo' => ['key' => null],
                ],
                'unique_field' => 'slug',
            ])
            ->assertSessionHasErrors('strategy');

        $import = $this->import->fresh();

        $this->assertNotEquals('latest-posts.csv', basename($import->get('path')));
    }

    #[Test]
    public function validation_error_is_thrown_without_any_mappings()
    {
        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->patch("/cp/utilities/importer/{$this->import->id()}", [
                'name' => 'Posts',
                'destination' => ['type' => 'entries', 'collection' => ['posts']],
                'strategy' => ['create', 'update'],
                'mappings' => [
                    'title' => ['key' => null],
                    'slug' => ['key' => null],
                    'content' => ['key' => null],
                    'author' => ['key' => null, 'related_field' => null],
                    'foo' => ['key' => null],
                ],
                'unique_field' => 'slug',
            ])
            ->assertSessionHasErrors('mappings');
    }

    #[Test]
    public function throws_validation_errors_for_mapping_fields()
    {
        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->patch("/cp/utilities/importer/{$this->import->id()}", [
                'name' => 'Posts',
                'destination' => ['type' => 'entries', 'collection' => ['posts']],
                'strategy' => ['create', 'update'],
                'mappings' => [
                    'author' => ['key' => 'Author Email', 'related_field' => null],
                ],
                'unique_field' => 'author',
            ])
            ->assertSessionHasErrors('mappings.author.related_field');
    }

    #[Test]
    public function validation_error_is_thrown_without_unique_field()
    {
        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->patch("/cp/utilities/importer/{$this->import->id()}", [
                'name' => 'Posts',
                'destination' => ['type' => 'entries', 'collection' => ['posts']],
                'strategy' => ['create', 'update'],
                'mappings' => [
                    'title' => ['key' => 'Title'],
                    'slug' => ['key' => 'Slug'],
                    'content' => ['key' => 'Content'],
                    'author' => ['key' => 'Author Email', 'related_field' => 'email'],
                    'foo' => ['key' => null],
                ],
                'unique_field' => null,
            ])
            ->assertSessionHasErrors('unique_field');
    }

    #[Test]
    public function validation_error_is_thrown_when_no_mapping_is_configured_for_unique_field()
    {
        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->patch("/cp/utilities/importer/{$this->import->id()}", [
                'name' => 'Posts',
                'destination' => ['type' => 'entries', 'collection' => ['posts']],
                'strategy' => ['create', 'update'],
                'mappings' => [
                    'title' => ['key' => 'Title'],
                    'slug' => ['key' => null],
                    'content' => ['key' => 'Content'],
                    'author' => ['key' => 'Author Email', 'related_field' => 'email'],
                    'foo' => ['key' => null],
                ],
                'unique_field' => 'slug',
            ])
            ->assertSessionHasErrors('unique_field');
    }
}
