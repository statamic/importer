<?php

namespace Imports;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Collection;
use Statamic\Facades\User;
use Statamic\Importer\Facades\Import;
use Statamic\Importer\Tests\TestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class ViewImportListingTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    public function setUp(): void
    {
        parent::setUp();

        File::deleteDirectory(storage_path('statamic/importer'));
    }

    #[Test]
    public function it_shows_a_list_of_imports()
    {
        Collection::make('posts')->save();

        Import::make()
            ->name('Posts')
            ->config(['type' => 'csv', 'path' => 'posts.csv', 'destination' => ['type' => 'entries', 'collection' => 'posts']])
            ->save();

        Import::make()
            ->name('Pages')
            ->config(['type' => 'xml', 'path' => 'pages.xml', 'destination' => ['type' => 'entries', 'collection' => 'pages']])
            ->save();

        $this
            ->actingAs(User::make()->makeSuper()->save())
            ->get('/cp/utilities/importer')
            ->assertOk()
            ->assertViewHas('imports', function ($imports) {
                return $imports->count() === 2;
            });
    }
}
