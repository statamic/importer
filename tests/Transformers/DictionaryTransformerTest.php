<?php

namespace Statamic\Importer\Tests\Transformers;

use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Collection;
use Statamic\Facades\User;
use Statamic\Importer\Facades\Import;
use Statamic\Importer\Tests\TestCase;
use Statamic\Importer\Transformers\DictionaryTransformer;
use Statamic\Importer\Transformers\UsersTransformer;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class DictionaryTransformerTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    public $collection;
    public $blueprint;
    public $field;
    public $import;

    public function setUp(): void
    {
        parent::setUp();

        $this->collection = tap(Collection::make('pages'))->save();

        $this->blueprint = $this->collection->entryBlueprint();
        $this->blueprint->ensureField('countries', ['type' => 'dictionary', 'dictionary' => 'countries'])->save();

        $this->field = $this->blueprint->field('countries');

        $this->import = Import::make();
    }

    #[Test]
    public function it_returns_keys()
    {
        $transformer = new DictionaryTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->field,
            config: []
        );

        $output = $transformer->transform('USA|CAN|FOO|DEU|GBR');

        $this->assertEquals(['USA', 'CAN', 'DEU', 'GBR'], $output);
    }
}
