<?php

namespace Statamic\Importer\Tests\Transformers;

use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Collection;
use Statamic\Facades\User;
use Statamic\Importer\Tests\TestCase;
use Statamic\Importer\Transformers\UsersTransformer;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class UsersTransformerTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    public $collection;
    public $blueprint;
    public $field;

    public function setUp(): void
    {
        parent::setUp();

        $this->collection = tap(Collection::make('pages'))->save();

        $this->blueprint = $this->collection->entryBlueprint();
        $this->blueprint->ensureField('authors', ['type' => 'users'])->save();

        $this->field = $this->blueprint->field('authors');
    }

    #[Test]
    public function it_returns_user_ids()
    {
        $transformer = new UsersTransformer($this->blueprint, $this->field, ['related_field' => 'id']);
        $output = $transformer->transform('one|two|three');

        $this->assertEquals(['one', 'two', 'three'], $output);
    }

    #[Test]
    public function it_finds_existing_users_by_name()
    {
        User::make()->id('one')->set('name', 'User One')->email('one@example.com')->save();
        User::make()->id('two')->set('name', 'User Two')->email('two@example.com')->save();
        User::make()->id('three')->set('name', 'User Three')->email('three@example.com')->save();

        $transformer = new UsersTransformer($this->blueprint, $this->field, ['related_field' => 'name']);
        $output = $transformer->transform('User One|User Two|User Three');

        $this->assertEquals(['one', 'two', 'three'], $output);
    }

    #[Test]
    public function it_finds_existing_users_by_email()
    {
        User::make()->id('one')->email('one@example.com')->save();
        User::make()->id('two')->email('two@example.com')->save();
        User::make()->id('three')->email('three@example.com')->save();

        $transformer = new UsersTransformer($this->blueprint, $this->field, ['related_field' => 'email']);
        $output = $transformer->transform('one@example.com|two@example.com|three@example.com');

        $this->assertEquals(['one', 'two', 'three'], $output);
    }

    #[Test]
    public function it_create_new_users_using_email()
    {
        $this->assertNull(User::findByEmail('one@example.com'));
        $this->assertNull(User::findByEmail('two@example.com'));
        $this->assertNull(User::findByEmail('three@example.com'));

        $transformer = new UsersTransformer($this->blueprint, $this->field, ['related_field' => 'email', 'create_when_missing' => true]);
        $output = $transformer->transform('one@example.com|two@example.com|three@example.com');

        $this->assertCount(3, $output);

        $this->assertNotNull(User::findByEmail('one@example.com'));
        $this->assertNotNull(User::findByEmail('two@example.com'));
        $this->assertNotNull(User::findByEmail('three@example.com'));
    }

    #[Test]
    public function it_doesnt_create_new_users_using_email_when_create_when_missing_option_is_disabled()
    {
        $this->assertNull(User::findByEmail('one@example.com'));
        $this->assertNull(User::findByEmail('two@example.com'));
        $this->assertNull(User::findByEmail('three@example.com'));

        $transformer = new UsersTransformer($this->blueprint, $this->field, ['related_field' => 'email', 'create_when_missing' => false]);
        $output = $transformer->transform('one@example.com|two@example.com|three@example.com');

        $this->assertCount(0, $output);

        $this->assertNull(User::findByEmail('one@example.com'));
        $this->assertNull(User::findByEmail('two@example.com'));
        $this->assertNull(User::findByEmail('three@example.com'));
    }
}
