<?php

namespace Statamic\Importer\Tests\Transformers;

use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Collection;
use Statamic\Importer\Facades\Import;
use Statamic\Importer\Tests\TestCase;
use Statamic\Importer\Transformers\DateTransformer;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class DateTransformerTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    public $collection;
    public $blueprint;
    public $import;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collection = tap(Collection::make('pages'))->save();
        $this->blueprint = $this->collection->entryBlueprint();

        $this->import = Import::make();
    }

    #[Test]
    public function it_transforms_dates()
    {
        $this->blueprint->ensureField('the_date', ['type' => 'date', 'time_enabled' => false]);

        $transformer = new DateTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->blueprint->field('the_date'),
            config: []
        );

        $this->assertEquals('2024-10-31', $transformer->transform('2024-10-31'));
    }

    #[Test]
    public function it_transforms_dates_with_time_enabled()
    {
        $this->blueprint->ensureField('the_date', ['type' => 'date', 'time_enabled' => true]);

        $transformer = new DateTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->blueprint->field('the_date'),
            config: []
        );

        $this->assertEquals('2024-10-31 10:12', $transformer->transform('2024-10-31 10:12:34'));
    }

    #[Test]
    public function it_transforms_dates_with_custom_format()
    {
        $this->blueprint->ensureField('the_date', ['type' => 'date', 'format' => 'jS F Y']);

        $transformer = new DateTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->blueprint->field('the_date'),
            config: []
        );

        $this->assertEquals('31st October 2024', $transformer->transform('2024-10-31'));
    }

    #[Test]
    public function it_transforms_date_range()
    {
        $this->blueprint->ensureField('the_date', ['type' => 'date', 'mode' => 'range', 'time_enabled' => false]);

        $transformer = new DateTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->blueprint->field('the_date'),
            config: ['end' => 'The End Date'],
            values: ['The End Date' => '2023-12-20']
        );

        $this->assertEquals([
            'start' => '2023-10-02',
            'end' => '2023-12-20',
        ], $transformer->transform('2023-10-02'));
    }
}
