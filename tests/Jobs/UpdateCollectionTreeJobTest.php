<?php

namespace Statamic\Importer\Tests\Jobs;

use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Importer\Facades\Import;
use Statamic\Importer\Jobs\UpdateCollectionTreeJob;
use Statamic\Importer\Tests\TestCase;
use Statamic\Structures\CollectionStructure;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class UpdateCollectionTreeJobTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function updates_the_collection_tree()
    {
        $collection = tap(Collection::make('pages')->structure((new CollectionStructure)->expectsRoot(true)))->save();

        $one = tap(Entry::make()->collection('pages')->id('one'))->save();
        $two = tap(Entry::make()->collection('pages')->id('two'))->save();
        $three = tap(Entry::make()->collection('pages')->id('three'))->save();
        $four = tap(Entry::make()->collection('pages')->id('four'))->save();
        $five = tap(Entry::make()->collection('pages')->id('five'))->save();

        $tree = $collection->structure()->in('default');
        $tree->append($one)->append($two)->append($three)->append($four)->append($five)->save();

        $import = Import::make()->id('pages')->config(['destination' => ['collection' => 'pages']]);

        Cache::forever('importer.pages.parents', [
            ['id' => 'two', 'parent' => 'one'],
            ['id' => 'three', 'parent' => 'two'],
            ['id' => 'four', 'parent' => 'two'],
            ['id' => 'five', 'parent' => 'three'],
        ]);

        UpdateCollectionTreeJob::dispatch($import);

        $this->assertEquals([
            ['entry' => 'one'],
            [
                'entry' => 'two',
                'children' => [
                    ['entry' => 'three', 'children' => [['entry' => 'five']]],
                    ['entry' => 'four'],
                ],
            ],
        ], $collection->structure()->in('default')->tree());
    }
}
