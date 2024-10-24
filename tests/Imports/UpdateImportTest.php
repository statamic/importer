<?php

namespace Imports;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Collection;
use Statamic\Facades\User;
use Statamic\Importer\Facades\Import;
use Statamic\Importer\Tests\TestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class UpdateImportTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    public function setUp(): void
    {
        parent::setUp();

        File::deleteDirectory(storage_path('statamic/importer'));
    }

    #[Test]
    public function it_updates_an_import()
    {
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

        $import = Import::make()
            ->name('Posts')
            ->config(['type' => 'csv', 'path' => 'posts.csv', 'destination' => ['type' => 'entries', 'collection' => 'posts']]);

        $import->save();

        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->patch("/cp/utilities/importer/{$import->id()}", [
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
    }

    #[Test]
    public function cant_update_import_without_unique_field()
    {
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

        $import = Import::make()
            ->name('Posts')
            ->config(['type' => 'csv', 'path' => 'posts.csv', 'destination' => ['type' => 'entries', 'collection' => 'posts']]);

        $import->save();

        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->patch("/cp/utilities/importer/{$import->id()}", [
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
}
