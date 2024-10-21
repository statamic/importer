<?php

namespace Statamic\Importer\Tests\Transformers;

use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Collection;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Importer\Tests\TestCase;
use Statamic\Importer\Transformers\TermsTransformer;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class TermsTransformerTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    public $collection;
    public $blueprint;
    public $field;

    public function setUp(): void
    {
        parent::setUp();

        Taxonomy::make('categories')->sites(['default'])->save();

        $this->collection = tap(Collection::make('pages'))->save();

        $this->blueprint = $this->collection->entryBlueprint();
        $this->blueprint->ensureField('categories', ['type' => 'terms', 'taxonomies' => ['categories']])->save();

        $this->field = $this->blueprint->field('categories');
    }

    #[Test]
    public function it_finds_existing_terms()
    {
        Term::make()->taxonomy('categories')->slug('one')->set('title', 'Category One')->save();
        Term::make()->taxonomy('categories')->slug('two')->set('title', 'Category Two')->save();
        Term::make()->taxonomy('categories')->slug('three')->set('title', 'Category Three')->save();

        $transformer = new TermsTransformer($this->blueprint, $this->field, ['related_field' => 'title']);
        $output = $transformer->transform('Category One|Category Two|Category Three');

        $this->assertEquals(['categories::one', 'categories::two', 'categories::three'], $output);
    }

    #[Test]
    public function it_create_new_terms()
    {
        $this->assertNull(Term::query()->where('title', 'Category One')->first());
        $this->assertNull(Term::query()->where('title', 'Category Two')->first());
        $this->assertNull(Term::query()->where('title', 'Category Three')->first());

        $transformer = new TermsTransformer($this->blueprint, $this->field, ['related_field' => 'title', 'create_when_missing' => true]);
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

        $transformer = new TermsTransformer($this->blueprint, $this->field, ['related_field' => 'title', 'create_when_missing' => false]);
        $output = $transformer->transform('Category One|Category Two|Category Three');

        $this->assertCount(0, $output);

        $this->assertNull(Term::query()->where('title', 'Category One')->first());
        $this->assertNull(Term::query()->where('title', 'Category Two')->first());
        $this->assertNull(Term::query()->where('title', 'Category Three')->first());
    }
}
