<?php

namespace Statamic\Importer\Tests\Transformers;

use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Collection;
use Statamic\Importer\Facades\Import;
use Statamic\Importer\Tests\TestCase;
use Statamic\Importer\Transformers\DateTransformer;
use Statamic\Importer\Transformers\ToggleTransformer;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class ToggleTransformerTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    public $collection;
    public $blueprint;
    public $import;

    public function setUp(): void
    {
        parent::setUp();

        $this->collection = tap(Collection::make('pages'))->save();

        $this->blueprint = $this->collection->entryBlueprint();
        $this->blueprint->ensureField('featured', ['type' => 'toggle']);

        $this->import = Import::make();
    }

    #[Test]
    public function it_transforms_booleans()
    {
        $transformer = new ToggleTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->blueprint->field('featured'),
            config: ['format' => 'boolean']
        );

        $this->assertTrue($transformer->transform('1'));
        $this->assertTrue($transformer->transform('true'));

        $this->assertFalse($transformer->transform('0'));
        $this->assertFalse($transformer->transform('false'));
    }

    #[Test]
    public function it_transforms_strings()
    {
        $transformer = new ToggleTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->blueprint->field('featured'),
            config: ['format' => 'string', 'values' => ['true' => 'yes|aye|yep', 'false' => 'no']]
        );

        $this->assertTrue($transformer->transform('yes'));
        $this->assertTrue($transformer->transform('aye'));
        $this->assertTrue($transformer->transform('yep'));

        $this->assertFalse($transformer->transform('no'));
    }
}
