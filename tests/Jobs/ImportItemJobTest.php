<?php

namespace Statamic\Importer\Tests\Jobs;

use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Facades\User;
use Statamic\Importer\Facades\Import;
use Statamic\Importer\Jobs\ImportItemJob;
use Statamic\Importer\Tests\TestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class ImportItemJobTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    protected function setUp(): void
    {
        parent::setUp();

        $collection = tap(Collection::make('team'))->save();

        $collection->entryBlueprint()->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        ['handle' => 'first_name', 'field' => ['type' => 'text']],
                        ['handle' => 'last_name', 'field' => ['type' => 'text']],
                        ['handle' => 'email', 'field' => ['type' => 'text']],
                        ['handle' => 'role', 'field' => ['type' => 'text']],
                    ],
                ],
            ],
        ])->save();

        $taxonomy = tap(Taxonomy::make('tags'))->save();

        $taxonomy->termBlueprint()->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        ['handle' => 'title', 'field' => ['type' => 'text']],
                        ['handle' => 'foo', 'field' => ['type' => 'text']],
                    ],
                ],
            ],
        ])->save();

        User::blueprint()->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        ['handle' => 'first_name', 'field' => ['type' => 'text']],
                        ['handle' => 'last_name', 'field' => ['type' => 'text']],
                        ['handle' => 'email', 'field' => ['type' => 'text']],
                    ],
                ],
            ],
        ]);
    }

    #[Test]
    public function it_imports_a_new_entry()
    {
        $this->assertNull(Entry::query()->where('email', 'john.doe@example.com')->first());

        $import = Import::make()->config([
            'destination' => ['type' => 'entries', 'collection' => 'team', 'blueprint' => 'team'],
            'unique_field' => 'email',
            'mappings' => [
                'first_name' => ['key' => 'First Name'],
                'last_name' => ['key' => 'Last Name'],
                'email' => ['key' => 'Email'],
                'role' => ['key' => 'Role'],
            ],
            'strategy' => ['create'],
        ]);

        ImportItemJob::dispatch($import, [
            'First Name' => 'John',
            'Last Name' => 'Doe',
            'Email' => 'john.doe@example.com',
            'Role' => 'CEO',
        ]);

        $entry = Entry::query()->where('email', 'john.doe@example.com')->first();

        $this->assertNotNull($entry);
        $this->assertEquals('John', $entry->get('first_name'));
        $this->assertEquals('Doe', $entry->get('last_name'));
        $this->assertEquals('john.doe@example.com', $entry->get('email'));
        $this->assertEquals('CEO', $entry->get('role'));
    }

    #[Test]
    public function it_imports_a_new_entry_in_a_multisite()
    {
        $this->setSites([
            'en' => ['locale' => 'en', 'url' => '/'],
            'fr' => ['locale' => 'fr', 'url' => '/fr/'],
        ]);

        Collection::find('team')->sites(['en', 'fr']);

        $this->assertNull(Entry::query()->where('email', 'john.doe@example.com')->first());

        $import = Import::make()->config([
            'destination' => ['type' => 'entries', 'collection' => 'team', 'blueprint' => 'team', 'site' => 'fr'],
            'unique_field' => 'email',
            'mappings' => [
                'first_name' => ['key' => 'First Name'],
                'last_name' => ['key' => 'Last Name'],
                'email' => ['key' => 'Email'],
                'role' => ['key' => 'Role'],
            ],
            'strategy' => ['create'],
        ]);

        ImportItemJob::dispatch($import, [
            'First Name' => 'John',
            'Last Name' => 'Doe',
            'Email' => 'john.doe@example.com',
            'Role' => 'CEO',
        ]);

        $entry = Entry::query()->where('email', 'john.doe@example.com')->first();

        $this->assertNotNull($entry);
        $this->assertEquals('John', $entry->get('first_name'));
        $this->assertEquals('Doe', $entry->get('last_name'));
        $this->assertEquals('john.doe@example.com', $entry->get('email'));
        $this->assertEquals('CEO', $entry->get('role'));
        $this->assertEquals('fr', $entry->site());
    }

    #[Test]
    public function it_imports_a_new_entry_with_a_specific_blueprint()
    {
        Blueprint::make('volunteers')->setNamespace('collections/team')->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        ['handle' => 'first_name', 'field' => ['type' => 'text']],
                        ['handle' => 'last_name', 'field' => ['type' => 'text']],
                    ],
                ],
            ],
        ])->save();

        Blink::forget('collection-entry-blueprints-team');

        $this->assertNull(Entry::query()->where('email', 'John')->first());

        $import = Import::make()->config([
            'destination' => ['type' => 'entries', 'collection' => 'team', 'blueprint' => 'volunteers'],
            'unique_field' => 'last_name',
            'mappings' => [
                'first_name' => ['key' => 'First Name'],
                'last_name' => ['key' => 'Last Name'],
            ],
            'strategy' => ['create'],
        ]);

        ImportItemJob::dispatch($import, [
            'First Name' => 'John',
            'Last Name' => 'Doe',
        ]);

        $entry = Entry::query()->where('first_name', 'John')->first();

        $this->assertNotNull($entry);
        $this->assertEquals('John', $entry->get('first_name'));
        $this->assertEquals('Doe', $entry->get('last_name'));
        $this->assertEquals('volunteers', $entry->blueprint()->handle());
    }

    #[Test]
    public function it_doesnt_import_a_new_entry_when_creation_is_disabled()
    {
        $this->assertNull(Entry::query()->where('email', 'john.doe@example.com')->first());

        $import = Import::make()->config([
            'destination' => ['type' => 'entries', 'collection' => 'team', 'blueprint' => 'team'],
            'unique_field' => 'email',
            'mappings' => [
                'first_name' => ['key' => 'First Name'],
                'last_name' => ['key' => 'Last Name'],
                'email' => ['key' => 'Email'],
                'role' => ['key' => 'Role'],
            ],
            'strategy' => ['update'],
        ]);

        ImportItemJob::dispatch($import, [
            'First Name' => 'John',
            'Last Name' => 'Doe',
            'Email' => 'john.doe@example.com',
            'Role' => 'CEO',
        ]);

        $entry = Entry::query()->where('email', 'john.doe@example.com')->first();

        $this->assertNull($entry);
    }

    #[Test]
    public function it_updates_an_existing_entry()
    {
        $entry = Entry::make()->collection('team')->data(['email' => 'john.doe@example.com', 'role' => 'CTO']);
        $entry->save();

        $import = Import::make()->config([
            'destination' => ['type' => 'entries', 'collection' => 'team', 'blueprint' => 'team'],
            'unique_field' => 'email',
            'mappings' => [
                'first_name' => ['key' => 'First Name'],
                'last_name' => ['key' => 'Last Name'],
                'email' => ['key' => 'Email'],
                'role' => ['key' => 'Role'],
            ],
            'strategy' => ['update'],
        ]);

        ImportItemJob::dispatch($import, [
            'First Name' => 'John',
            'Last Name' => 'Doe',
            'Email' => 'john.doe@example.com',
            'Role' => 'CEO',
        ]);

        $entry->fresh();

        $this->assertNotNull($entry);
        $this->assertEquals('John', $entry->get('first_name'));
        $this->assertEquals('Doe', $entry->get('last_name'));
        $this->assertEquals('john.doe@example.com', $entry->get('email'));
        $this->assertEquals('CEO', $entry->get('role'));
    }

    #[Test]
    public function it_updates_an_existing_entry_when_entry_is_in_the_same_site()
    {
        $this->setSites([
            'en' => ['locale' => 'en', 'url' => '/'],
            'fr' => ['locale' => 'fr', 'url' => '/fr/'],
        ]);

        Collection::find('team')->sites(['en', 'fr']);

        $entry = Entry::make()->collection('team')->locale('fr')->data(['email' => 'john.doe@example.com', 'role' => 'CTO']);
        $entry->save();

        $import = Import::make()->config([
            'destination' => ['type' => 'entries', 'collection' => 'team', 'blueprint' => 'team', 'site' => 'fr'],
            'unique_field' => 'email',
            'mappings' => [
                'first_name' => ['key' => 'First Name'],
                'last_name' => ['key' => 'Last Name'],
                'email' => ['key' => 'Email'],
                'role' => ['key' => 'Role'],
            ],
            'strategy' => ['update'],
        ]);

        ImportItemJob::dispatch($import, [
            'First Name' => 'John',
            'Last Name' => 'Doe',
            'Email' => 'john.doe@example.com',
            'Role' => 'CEO',
        ]);

        $entry->fresh();

        $this->assertNotNull($entry);
        $this->assertEquals('John', $entry->get('first_name'));
        $this->assertEquals('Doe', $entry->get('last_name'));
        $this->assertEquals('john.doe@example.com', $entry->get('email'));
        $this->assertEquals('CEO', $entry->get('role'));
        $this->assertEquals('fr', $entry->site());
    }

    #[Test]
    public function it_doesnt_update_an_existing_entry_when_entry_is_in_a_different_site()
    {
        $this->setSites([
            'en' => ['locale' => 'en', 'url' => '/'],
            'fr' => ['locale' => 'fr', 'url' => '/fr/'],
        ]);

        Collection::find('team')->sites(['en', 'fr']);

        $entry = Entry::make()->collection('team')->locale('en')->data(['email' => 'john.doe@example.com', 'role' => 'CTO']);
        $entry->save();

        $import = Import::make()->config([
            'destination' => ['type' => 'entries', 'collection' => 'team', 'blueprint' => 'team', 'site' => 'fr'],
            'unique_field' => 'email',
            'mappings' => [
                'first_name' => ['key' => 'First Name'],
                'last_name' => ['key' => 'Last Name'],
                'email' => ['key' => 'Email'],
                'role' => ['key' => 'Role'],
            ],
            'strategy' => ['create', 'update'],
        ]);

        ImportItemJob::dispatch($import, [
            'First Name' => 'John',
            'Last Name' => 'Doe',
            'Email' => 'john.doe@example.com',
            'Role' => 'CEO',
        ]);

        $entry->fresh();

        // The existing entry will remain unchanged.
        $this->assertNotNull($entry);
        $this->assertNull($entry->get('first_name'));
        $this->assertNull($entry->get('last_name'));
        $this->assertEquals('john.doe@example.com', $entry->get('email'));
        $this->assertEquals('CTO', $entry->get('role'));
        $this->assertEquals('en', $entry->site());

        // And a new entry will be created in the destination site.
        $newEntry = Entry::query()->where('site', 'fr')->where('email', 'john.doe@example.com')->first();

        $this->assertNotNull($newEntry);
        $this->assertEquals('John', $newEntry->get('first_name'));
        $this->assertEquals('Doe', $newEntry->get('last_name'));
        $this->assertEquals('john.doe@example.com', $newEntry->get('email'));
        $this->assertEquals('CEO', $newEntry->get('role'));
        $this->assertEquals('fr', $newEntry->site());
    }

    #[Test]
    public function it_doesnt_update_an_existing_entry_when_updating_is_disabled()
    {
        $entry = Entry::make()->collection('team')->data(['email' => 'john.doe@example.com', 'role' => 'CTO']);
        $entry->save();

        $import = Import::make()->config([
            'destination' => ['type' => 'entries', 'collection' => 'team', 'blueprint' => 'team'],
            'unique_field' => 'email',
            'mappings' => [
                'first_name' => ['key' => 'First Name'],
                'last_name' => ['key' => 'Last Name'],
                'email' => ['key' => 'Email'],
                'role' => ['key' => 'Role'],
            ],
            'strategy' => ['create'],
        ]);

        ImportItemJob::dispatch($import, [
            'First Name' => 'John',
            'Last Name' => 'Doe',
            'Email' => 'john.doe@example.com',
            'Role' => 'CEO',
        ]);

        $entry->fresh();

        $this->assertNotNull($entry);
        $this->assertNull($entry->get('first_name'));
        $this->assertNull($entry->get('last_name'));
        $this->assertEquals('john.doe@example.com', $entry->get('email'));
        $this->assertEquals('CTO', $entry->get('role'));
    }

    #[Test]
    public function it_imports_a_new_term()
    {
        $this->assertNull(Term::query()->where('title', 'Statamic')->first());

        $import = Import::make()->config([
            'destination' => ['type' => 'terms', 'taxonomy' => 'tags', 'blueprint' => 'tag'],
            'unique_field' => 'title',
            'mappings' => [
                'title' => ['key' => 'Title'],
                'slug' => ['key' => 'Slug'],
            ],
            'strategy' => ['create'],
        ]);

        ImportItemJob::dispatch($import, [
            'Title' => 'Statamic',
            'Slug' => 'statamic',
        ]);

        $term = Term::query()->where('title', 'Statamic')->first();

        $this->assertNotNull($term);
        $this->assertEquals('statamic', $term->slug());
        $this->assertEquals('Statamic', $term->get('title'));
    }

    #[Test]
    public function it_imports_a_new_term_in_a_multisite_into_the_default_site()
    {
        $this->setSites([
            'en' => ['locale' => 'en', 'url' => '/'],
            'fr' => ['locale' => 'fr', 'url' => '/fr/'],
        ]);

        Taxonomy::find('tags')->sites(['en', 'fr']);

        $this->assertNull(Term::query()->where('title', 'Statamic')->first());

        $import = Import::make()->config([
            'destination' => ['type' => 'terms', 'taxonomy' => 'tags', 'blueprint' => 'tag', 'site' => 'en'],
            'unique_field' => 'title',
            'mappings' => [
                'title' => ['key' => 'Title'],
                'slug' => ['key' => 'Slug'],
            ],
            'strategy' => ['create'],
        ]);

        ImportItemJob::dispatch($import, [
            'Title' => 'Statamic',
            'Slug' => 'statamic',
        ]);

        $term = Term::query()
            ->where('site', 'en')
            ->where('title', 'Statamic')
            ->first();

        $this->assertNotNull($term);
        $this->assertEquals('statamic', $term->slug());
        $this->assertEquals('Statamic', $term->get('title'));
        $this->assertEquals('en', $term->site());
    }

    #[Test]
    public function it_imports_a_new_term_in_a_multisite_into_a_specific_site()
    {
        $this->setSites([
            'en' => ['locale' => 'en', 'url' => '/'],
            'fr' => ['locale' => 'fr', 'url' => '/fr/'],
        ]);

        Taxonomy::find('tags')->sites(['en', 'fr']);

        $this->assertNull(Term::query()->where('title', 'Statamic')->first());

        $import = Import::make()->config([
            'destination' => ['type' => 'terms', 'taxonomy' => 'tags', 'blueprint' => 'tag', 'site' => 'fr'],
            'unique_field' => 'title',
            'mappings' => [
                'title' => ['key' => 'Title'],
                'slug' => ['key' => 'Slug'],
            ],
            'strategy' => ['create'],
        ]);

        ImportItemJob::dispatch($import, [
            'Title' => 'Statamic',
            'Slug' => 'statamic',
        ]);

        $term = Term::query()
            ->where('site', 'fr')
            ->where('title', 'Statamic')
            ->first();

        $this->assertNotNull($term);

        // Both the default site and the chosen site should have the same data
        // (because of the way taxonomies work).
        $en = $term->in('en');
        $this->assertEquals('statamic', $en->slug());
        $this->assertEquals('Statamic', $en->get('title'));
        $this->assertEquals('en', $en->site());

        $fr = $term->in('fr');
        $this->assertEquals('statamic', $fr->slug());
        $this->assertEquals('Statamic', $fr->get('title'));
        $this->assertEquals('fr', $fr->site());
    }

    #[Test]
    public function it_imports_a_new_term_with_a_specific_blueprint()
    {
        Blueprint::make('special_tag')->setNamespace('taxonomies/tags')->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        ['handle' => 'title', 'field' => ['type' => 'text']],
                    ],
                ],
            ],
        ])->save();

        $this->assertNull(Term::query()->where('title', 'Statamic')->first());

        $import = Import::make()->config([
            'destination' => ['type' => 'terms', 'taxonomy' => 'tags', 'blueprint' => 'special_tag'],
            'unique_field' => 'title',
            'mappings' => [
                'title' => ['key' => 'Title'],
                'slug' => ['key' => 'Slug'],
            ],
            'strategy' => ['create'],
        ]);

        ImportItemJob::dispatch($import, [
            'Title' => 'Statamic',
            'Slug' => 'statamic',
        ]);

        $term = Term::query()->where('title', 'Statamic')->first();

        $this->assertNotNull($term);
        $this->assertEquals('statamic', $term->slug());
        $this->assertEquals('Statamic', $term->get('title'));
        $this->assertEquals('special_tag', $term->blueprint()->handle());
    }

    #[Test]
    public function it_doesnt_import_a_new_term_when_creation_is_disabled()
    {
        $this->assertNull(Term::query()->where('title', 'Statamic')->first());

        $import = Import::make()->config([
            'destination' => ['type' => 'terms', 'taxonomy' => 'tags', 'blueprint' => 'tag'],
            'unique_field' => 'title',
            'mappings' => [
                'title' => ['key' => 'Title'],
                'slug' => ['key' => 'Slug'],
            ],
            'strategy' => ['update'],
        ]);

        ImportItemJob::dispatch($import, [
            'Title' => 'Statamic',
            'Slug' => 'statamic',
        ]);

        $this->assertNull(Term::query()->where('title', 'Statamic')->first());
    }

    #[Test]
    public function it_updates_an_existing_term()
    {
        $term = Term::make()->taxonomy('tags')->slug('statamic')->set('title', 'Statamic')->set('foo', 'bar');
        $term->save();

        $import = Import::make()->config([
            'destination' => ['type' => 'terms', 'taxonomy' => 'tags', 'blueprint' => 'tag'],
            'unique_field' => 'title',
            'mappings' => [
                'title' => ['key' => 'Title'],
                'slug' => ['key' => 'Slug'],
                'foo' => ['key' => 'Foo'],
            ],
            'strategy' => ['update'],
        ]);

        ImportItemJob::dispatch($import, [
            'Title' => 'Statamic',
            'Slug' => 'statamic',
            'Foo' => 'Baz',
        ]);

        $term = $term->fresh();

        $this->assertNotNull($term);
        $this->assertEquals('statamic', $term->slug());
        $this->assertEquals('Statamic', $term->get('title'));
        $this->assertEquals('Baz', $term->get('foo'));
    }

    #[Test]
    public function it_updates_an_existing_term_in_a_multisite_with_the_same_slug()
    {
        $this->setSites([
            'en' => ['locale' => 'en', 'url' => '/'],
            'fr' => ['locale' => 'fr', 'url' => '/fr/'],
        ]);

        $term = Term::make()->taxonomy('tags')->slug('statamic')->set('title', 'Statamic')->set('foo', 'bar');
        $term->save();

        $import = Import::make()->config([
            'destination' => ['type' => 'terms', 'taxonomy' => 'tags', 'blueprint' => 'tag', 'site' => 'fr'],
            'unique_field' => 'title',
            'mappings' => [
                'title' => ['key' => 'Title'],
                'slug' => ['key' => 'Slug'],
                'foo' => ['key' => 'Foo'],
            ],
            'strategy' => ['update'],
        ]);

        ImportItemJob::dispatch($import, [
            'Title' => 'Statamic',
            'Slug' => 'statamic',
            'Foo' => 'Baz',
        ]);

        $term = $term->fresh();

        // The importer is updating the French localization of the term, so the English
        // localization should stay the same.
        $en = $term->in('en');
        $this->assertEquals('statamic', $en->slug());
        $this->assertEquals('Statamic', $en->get('title'));
        $this->assertEquals('bar', $en->get('foo'));
        $this->assertEquals('en', $en->site());

        $fr = $term->in('fr');
        $this->assertEquals('statamic', $fr->slug());
        $this->assertEquals('Statamic', $fr->get('title'));
        $this->assertEquals('Baz', $fr->get('foo'));
        $this->assertEquals('fr', $fr->site());
    }

    #[Test]
    public function it_updates_an_existing_term_in_a_multisite_with_the_default_slug_mapping()
    {
        $this->setSites([
            'en' => ['locale' => 'en', 'url' => '/'],
            'fr' => ['locale' => 'fr', 'url' => '/fr/'],
        ]);

        $term = Term::make()->taxonomy('tags')->slug('statamic')->set('title', 'Statamic')->set('foo', 'bar');
        $term->save();

        $import = Import::make()->config([
            'destination' => ['type' => 'terms', 'taxonomy' => 'tags', 'blueprint' => 'tag', 'site' => 'fr'],
            'unique_field' => 'title',
            'mappings' => [
                'title' => ['key' => 'Title'],
                'slug' => ['key' => 'Slug'],
                'default_slug' => ['key' => 'Default Slug'],
                'foo' => ['key' => 'Foo'],
            ],
            'strategy' => ['update'],
        ]);

        ImportItemJob::dispatch($import, [
            'Title' => 'Statique Dynamique',
            'Slug' => 'statique-dynamique',
            'Default Slug' => 'statamic',
            'Foo' => 'Baz',
        ]);

        $term = $term->fresh();

        // The importer is updating the French localization of the term, so the English
        // localization should stay the same.
        $en = $term->in('en');
        $this->assertEquals('statamic', $en->slug());
        $this->assertEquals('Statamic', $en->get('title'));
        $this->assertEquals('bar', $en->get('foo'));
        $this->assertEquals('en', $en->site());

        $fr = $term->in('fr');
        $this->assertEquals('statique-dynamique', $fr->slug());
        $this->assertEquals('Statique Dynamique', $fr->get('title'));
        $this->assertEquals('Baz', $fr->get('foo'));
        $this->assertEquals('fr', $fr->site());
    }

    #[Test]
    public function it_doesnt_update_an_existing_term_when_updating_is_disabled()
    {
        $term = Term::make()->taxonomy('tags')->slug('statamic')->set('title', 'Statamic')->set('foo', 'bar');
        $term->save();

        $import = Import::make()->config([
            'destination' => ['type' => 'terms', 'taxonomy' => 'tags', 'blueprint' => 'tag'],
            'unique_field' => 'title',
            'mappings' => [
                'title' => ['key' => 'Title'],
                'slug' => ['key' => 'Slug'],
                'foo' => ['key' => 'Foo'],
            ],
            'strategy' => ['create'],
        ]);

        ImportItemJob::dispatch($import, [
            'Title' => 'Statamic',
            'Slug' => 'statamic',
            'Foo' => 'Baz',
        ]);

        $term->fresh();

        $this->assertNotNull($term);
        $this->assertEquals('statamic', $term->slug());
        $this->assertEquals('Statamic', $term->get('title'));
        $this->assertEquals('bar', $term->get('foo'));
    }

    #[Test]
    public function it_imports_a_new_user()
    {
        $this->assertNull(User::findByEmail('john.doe@example.com'));

        $import = Import::make()->config([
            'destination' => ['type' => 'users'],
            'unique_field' => 'email',
            'mappings' => [
                'first_name' => ['key' => 'First Name'],
                'last_name' => ['key' => 'Last Name'],
                'email' => ['key' => 'Email'],
            ],
            'strategy' => ['create'],
        ]);

        ImportItemJob::dispatch($import, [
            'First Name' => 'John',
            'Last Name' => 'Doe',
            'Email' => 'john.doe@example.com',
        ]);

        $user = User::findByEmail('john.doe@example.com');

        $this->assertNotNull($user);
        $this->assertEquals('John', $user->get('first_name'));
        $this->assertEquals('Doe', $user->get('last_name'));
        $this->assertEquals('john.doe@example.com', $user->email());
    }

    #[Test]
    public function it_doesnt_import_a_new_user_when_creation_is_disabled()
    {
        $this->assertNull(User::findByEmail('john.doe@example.com'));

        $import = Import::make()->config([
            'destination' => ['type' => 'users'],
            'unique_field' => 'email',
            'mappings' => [
                'first_name' => ['key' => 'First Name'],
                'last_name' => ['key' => 'Last Name'],
                'email' => ['key' => 'Email'],
            ],
            'strategy' => ['update'],
        ]);

        ImportItemJob::dispatch($import, [
            'First Name' => 'John',
            'Last Name' => 'Doe',
            'Email' => 'john.doe@example.com',
        ]);

        $this->assertNull(User::findByEmail('john.doe@example.com'));
    }

    #[Test]
    public function it_updates_an_existing_user()
    {
        $user = User::make()->email('john.doe@example.com');
        $user->save();

        $import = Import::make()->config([
            'destination' => ['type' => 'users'],
            'unique_field' => 'email',
            'mappings' => [
                'first_name' => ['key' => 'First Name'],
                'last_name' => ['key' => 'Last Name'],
                'email' => ['key' => 'Email'],
            ],
            'strategy' => ['update'],
        ]);

        ImportItemJob::dispatch($import, [
            'First Name' => 'John',
            'Last Name' => 'Doe',
            'Email' => 'john.doe@example.com',
        ]);

        $user->fresh();

        $this->assertNotNull($user);
        $this->assertEquals('John', $user->get('first_name'));
        $this->assertEquals('Doe', $user->get('last_name'));
        $this->assertEquals('john.doe@example.com', $user->email());
    }

    #[Test]
    public function it_doesnt_update_an_existing_user_when_updating_is_disabled()
    {
        $user = User::make()->email('john.doe@example.com');
        $user->save();

        $import = Import::make()->config([
            'destination' => ['type' => 'users'],
            'unique_field' => 'email',
            'mappings' => [
                'first_name' => ['key' => 'First Name'],
                'last_name' => ['key' => 'Last Name'],
                'email' => ['key' => 'Email'],
            ],
            'strategy' => ['create'],
        ]);

        ImportItemJob::dispatch($import, [
            'First Name' => 'John',
            'Last Name' => 'Doe',
            'Email' => 'john.doe@example.com',
        ]);

        $user->fresh();

        $this->assertNotNull($user);
        $this->assertNull($user->get('first_name'));
        $this->assertNull($user->get('last_name'));
        $this->assertEquals('john.doe@example.com', $user->email());
    }
}
