<?php

namespace Statamic\Importer\Tests\Transformers;

use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Collection;
use Statamic\Importer\Facades\Import;
use Statamic\Importer\Tests\TestCase;
use Statamic\Importer\Transformers\ListTransformer;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class ListTransformerTest extends TestCase
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
        $this->blueprint->ensureField('ice_cream_flavors', ['type' => 'list'])->save();

        $this->field = $this->blueprint->field('ice_cream_flavors');

        $this->import = Import::make();
    }

    #[Test]
    public function it_returns_an_array()
    {
        $transformer = new ListTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->field,
            config: []
        );

        $output = $transformer->transform('Vanilla|Chocolate|Honeycomb|Strawberry|Pistachio');

        $this->assertEquals(['Vanilla', 'Chocolate', 'Honeycomb', 'Strawberry', 'Pistachio'], $output);
    }

    #[Test]
    public function it_unserializes_arrays_without_instantiating_objects()
    {
        $transformer = new ListTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->field,
            config: []
        );

        $output = $transformer->transform('a:3:{i:0;s:7:"Vanilla";i:1;O:7:"Closure":0:{}i:2;s:9:"Chocolate";}');

        $this->assertEquals(['Vanilla', 'Chocolate'], $output);
    }
}
