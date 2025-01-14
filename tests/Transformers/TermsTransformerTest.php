<?php

namespace Statamic\Importer\Tests\Transformers;

use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Importer\Facades\Import;
use Statamic\Importer\Tests\TestCase;
use Statamic\Importer\Transformers\TermsTransformer;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class TermsTransformerTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    public $collection;
    public $blueprint;
    public $field;
    public $import;

    protected function setUp(): void
    {
        parent::setUp();

        Taxonomy::make('categories')->sites(['default'])->save();

        $this->collection = tap(Collection::make('pages'))->save();

        $this->blueprint = $this->collection->entryBlueprint();
        $this->blueprint->ensureField('categories', ['type' => 'terms', 'taxonomies' => ['categories']])->save();

        $this->field = $this->blueprint->field('categories');

        $this->import = Import::make();
    }

    #[Test]
    public function it_finds_existing_terms()
    {
        Term::make()->taxonomy('categories')->slug('one')->set('title', 'Category One')->save();
        Term::make()->taxonomy('categories')->slug('two')->set('title', 'Category Two')->save();
        Term::make()->taxonomy('categories')->slug('three')->set('title', 'Category Three')->save();

        $transformer = new TermsTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->field,
            config: ['related_field' => 'title']
        );

        $output = $transformer->transform('Category One|Category Two|Category Three');

        $this->assertEquals(['one', 'two', 'three'], $output);
    }

    #[Test]
    public function it_finds_existing_terms_across_multiple_taxonomies()
    {
        Taxonomy::make('tags')->sites(['default'])->save();

        Term::make()->taxonomy('categories')->slug('one')->set('title', 'Category One')->save();
        Term::make()->taxonomy('categories')->slug('two')->set('title', 'Category Two')->save();
        Term::make()->taxonomy('categories')->slug('three')->set('title', 'Category Three')->save();
        Term::make()->taxonomy('tags')->slug('foo')->set('title', 'Foo')->save();

        $blueprint = Blueprint::find($this->blueprint->fullyQualifiedHandle());
        $blueprint->ensureField('stuff', ['type' => 'terms', 'taxonomies' => ['categories', 'tags']])->save();

        $transformer = new TermsTransformer(
            import: $this->import,
            blueprint: $blueprint,
            field: $blueprint->field('stuff'),
            config: ['related_field' => 'title']
        );

        $output = $transformer->transform('Category One|Category Two|Category Three|Foo');

        $this->assertEquals(['categories::one', 'categories::two', 'categories::three', 'tags::foo'], $output);
    }

    #[Test]
    public function it_create_new_terms()
    {
        $this->assertNull(Term::query()->where('title', 'Category One')->first());
        $this->assertNull(Term::query()->where('title', 'Category Two')->first());
        $this->assertNull(Term::query()->where('title', 'Category Three')->first());

        $transformer = new TermsTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->field,
            config: [
                'related_field' => 'title',
                'create_when_missing' => true,
            ]
        );

        $output = $transformer->transform('Category One|Category Two|Category Three');

        $this->assertCount(3, $output);

        $this->assertNotNull(Term::query()->where('title', 'Category One')->first());
        $this->assertNotNull(Term::query()->where('title', 'Category Two')->first());
        $this->assertNotNull(Term::query()->where('title', 'Category Three')->first());
    }

    #[Test]
    public function it_doesnt_create_new_terms_when_create_when_missing_option_is_disabled()
    {
        $this->assertNull(Term::query()->where('title', 'Category One')->first());
        $this->assertNull(Term::query()->where('title', 'Category Two')->first());
        $this->assertNull(Term::query()->where('title', 'Category Three')->first());

        $transformer = new TermsTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->field,
            config: [
                'related_field' => 'title',
                'create_when_missing' => false,
            ]
        );

        $output = $transformer->transform('Category One|Category Two|Category Three');

        $this->assertCount(0, $output);

        $this->assertNull(Term::query()->where('title', 'Category One')->first());
        $this->assertNull(Term::query()->where('title', 'Category Two')->first());
        $this->assertNull(Term::query()->where('title', 'Category Three')->first());
    }
}
