<?php

namespace Statamic\Importer\Tests\Transformers;

use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Importer\Facades\Import;
use Statamic\Importer\Tests\TestCase;
use Statamic\Importer\Transformers\EntriesTransformer;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class EntriesTransformerTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    public $collection;
    public $blueprint;
    public $field;
    public $import;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collection = tap(Collection::make('pages'))->save();

        $this->blueprint = $this->collection->entryBlueprint();
        $this->blueprint->ensureField('other_entries', ['type' => 'entries', 'collections' => ['pages']])->save();

        $this->field = $this->blueprint->field('other_entries');

        $this->import = Import::make();
    }

    #[Test]
    public function it_returns_entry_ids()
    {
        $transformer = new EntriesTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->field,
            config: ['related_field' => 'id']
        );

        $output = $transformer->transform('one|two|three');

        $this->assertEquals(['one', 'two', 'three'], $output);
    }

    #[Test]
    public function it_finds_existing_entries()
    {
        Entry::make()->collection('pages')->id('one')->set('title', 'Entry One')->save();
        Entry::make()->collection('pages')->id('two')->set('title', 'Entry Two')->save();
        Entry::make()->collection('pages')->id('three')->set('title', 'Entry Three')->save();

        $transformer = new EntriesTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->field,
            config: ['related_field' => 'title']
        );

        $output = $transformer->transform('Entry One|Entry Two|Entry Three');

        $this->assertEquals(['one', 'two', 'three'], $output);
    }

    #[Test]
    public function it_only_finds_existing_entries_in_the_chosen_site()
    {
        $this->setSites([
            'en' => ['locale' => 'en', 'url' => '/'],
            'de' => ['locale' => 'de', 'url' => '/de/'],
        ]);

        $this->import->config(['destination' => ['site' => 'de']]);

        Entry::make()->collection('pages')->locale('de')->id('ein')->set('title', 'Entry Ein')->save();
        Entry::make()->collection('pages')->locale('de')->id('zwei')->set('title', 'Entry Zwei')->save();
        Entry::make()->collection('pages')->locale('en')->id('three')->set('title', 'Entry Three')->save();

        $transformer = new EntriesTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->field,
            config: ['related_field' => 'title']
        );

        $output = $transformer->transform('Entry Ein|Entry Zwei|Entry Three');

        $this->assertEquals(['ein', 'zwei'], $output);
    }

    #[Test]
    public function it_finds_entries_on_any_sites_when_select_across_sites_is_enabled()
    {
        $this->setSites([
            'en' => ['locale' => 'en', 'url' => '/'],
            'de' => ['locale' => 'de', 'url' => '/de/'],
        ]);

        $this->import->config(['destination' => ['site' => 'de']]);

        $blueprint = Blueprint::find($this->blueprint->fullyQualifiedHandle());
        $blueprint->ensureFieldHasConfig('other_entries', ['type' => 'entries', 'collections' => ['pages'], 'select_across_sites' => true]);
        
        $this->field = $blueprint->field('other_entries');

        Entry::make()->collection('pages')->locale('de')->id('ein')->set('title', 'Entry Ein')->save();
        Entry::make()->collection('pages')->locale('de')->id('zwei')->set('title', 'Entry Zwei')->save();
        Entry::make()->collection('pages')->locale('en')->id('three')->set('title', 'Entry Three')->save();

        $transformer = new EntriesTransformer(
            import: $this->import,
            blueprint: $blueprint,
            field: $this->field,
            config: ['related_field' => 'title']
        );

        $output = $transformer->transform('Entry Ein|Entry Zwei|Entry Three');

        $this->assertEquals(['ein', 'zwei', 'three'], $output);
    }

    #[Test]
    public function it_create_new_entries()
    {
        $this->assertNull(Entry::query()->where('title', 'Entry One')->first());
        $this->assertNull(Entry::query()->where('title', 'Entry Two')->first());
        $this->assertNull(Entry::query()->where('title', 'Entry Three')->first());

        $transformer = new EntriesTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->field,
            config: [
                'related_field' => 'title',
                'create_when_missing' => true,
            ]
        );

        $output = $transformer->transform('Entry One|Entry Two|Entry Three');

        $this->assertCount(3, $output);

        $this->assertNotNull(Entry::query()->where('title', 'Entry One')->first());
        $this->assertNotNull(Entry::query()->where('title', 'Entry Two')->first());
        $this->assertNotNull(Entry::query()->where('title', 'Entry Three')->first());
    }

    #[Test]
    public function it_doesnt_create_new_entries_when_create_when_missing_option_is_disabled()
    {
        $this->assertNull(Entry::query()->where('title', 'Entry One')->first());
        $this->assertNull(Entry::query()->where('title', 'Entry Two')->first());
        $this->assertNull(Entry::query()->where('title', 'Entry Three')->first());

        $transformer = new EntriesTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->field,
            config: [
                'related_field' => 'title',
                'create_when_missing' => false,
            ]
        );

        $output = $transformer->transform('Entry One|Entry Two|Entry Three');

        $this->assertCount(0, $output);

        $this->assertNull(Entry::query()->where('title', 'Entry One')->first());
        $this->assertNull(Entry::query()->where('title', 'Entry Two')->first());
        $this->assertNull(Entry::query()->where('title', 'Entry Three')->first());
    }

    #[Test]
    public function it_create_new_entries_in_selected_site()
    {
        $this->setSites([
            'en' => ['locale' => 'en', 'url' => '/'],
            'de' => ['locale' => 'de', 'url' => '/de/'],
        ]);

        $this->import->config(['destination' => ['site' => 'de']]);

        $this->assertNull(Entry::query()->where('title', 'Entry One')->first());
        $this->assertNull(Entry::query()->where('title', 'Entry Two')->first());
        $this->assertNull(Entry::query()->where('title', 'Entry Three')->first());

        $transformer = new EntriesTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->field,
            config: [
                'related_field' => 'title',
                'create_when_missing' => true,
            ]
        );

        $output = $transformer->transform('Entry One|Entry Two|Entry Three');

        $this->assertCount(3, $output);

        $this->assertNotNull(Entry::query()->where('locale', 'de')->where('title', 'Entry One')->first());
        $this->assertNotNull(Entry::query()->where('locale', 'de')->where('title', 'Entry Two')->first());
        $this->assertNotNull(Entry::query()->where('locale', 'de')->where('title', 'Entry Three')->first());
    }
}
