<?php

namespace Statamic\Importer\Tests\Jobs;

use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Facades\User;
use Statamic\Importer\Jobs\ImportItemJob;
use Statamic\Importer\Tests\TestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class ImportItemJobTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    public function setUp(): void
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

        ImportItemJob::dispatch(
            config: [
                'destination' => ['type' => 'entries', 'collection' => 'team'],
                'unique_key' => 'email',
                'mappings' => [
                    'first_name' => ['key' => 'First Name'],
                    'last_name' => ['key' => 'Last Name'],
                    'email' => ['key' => 'Email'],
                    'role' => ['key' => 'Role'],
                ],
            ],
            item: [
                'First Name' => 'John',
                'Last Name' => 'Doe',
                'Email' => 'john.doe@example.com',
                'Role' => 'CEO',
            ]
        );

        $entry = Entry::query()->where('email', 'john.doe@example.com')->first();

        $this->assertNotNull($entry);
        $this->assertEquals('John', $entry->get('first_name'));
        $this->assertEquals('Doe', $entry->get('last_name'));
        $this->assertEquals('john.doe@example.com', $entry->get('email'));
        $this->assertEquals('CEO', $entry->get('role'));
    }

    #[Test]
    public function it_updates_an_existing_entry()
    {
        $entry = Entry::make()->collection('team')->data(['email' => 'john.doe@example.com', 'role' => 'CTO']);
        $entry->save();

        ImportItemJob::dispatch(
            config: [
                'destination' => ['type' => 'entries', 'collection' => 'team'],
                'unique_key' => 'email',
                'mappings' => [
                    'first_name' => ['key' => 'First Name'],
                    'last_name' => ['key' => 'Last Name'],
                    'email' => ['key' => 'Email'],
                    'role' => ['key' => 'Role'],
                ],
            ],
            item: [
                'First Name' => 'John',
                'Last Name' => 'Doe',
                'Email' => 'john.doe@example.com',
                'Role' => 'CEO',
            ]
        );

        $entry->fresh();

        $this->assertNotNull($entry);
        $this->assertEquals('John', $entry->get('first_name'));
        $this->assertEquals('Doe', $entry->get('last_name'));
        $this->assertEquals('john.doe@example.com', $entry->get('email'));
        $this->assertEquals('CEO', $entry->get('role'));
    }

    #[Test]
    public function it_imports_a_new_term()
    {
        $this->assertNull(Term::query()->where('title', 'Statamic')->first());

        ImportItemJob::dispatch(
            config: [
                'destination' => ['type' => 'terms', 'taxonomy' => 'tags'],
                'unique_key' => 'title',
                'mappings' => [
                    'title' => ['key' => 'Title'],
                ],
            ],
            item: [
                'Title' => 'Statamic',
            ]
        );

        $term = Term::query()->where('title', 'Statamic')->first();

        $this->assertNotNull($term);
        $this->assertEquals('statamic', $term->slug());
        $this->assertEquals('Statamic', $term->get('title'));
    }

    #[Test]
    public function it_updates_an_existing_term()
    {
        $term = Term::make()->taxonomy('tags')->slug('statamic')->set('title', 'Statamic');
        $term->save();

        ImportItemJob::dispatch(
            config: [
                'destination' => ['type' => 'terms', 'taxonomy' => 'tags'],
                'unique_key' => 'title',
                'mappings' => [
                    'title' => ['key' => 'Title'],
                ],
            ],
            item: [
                'Title' => 'Statamic',
            ]
        );

        $term->fresh();

        $this->assertNotNull($term);
        $this->assertEquals('statamic', $term->slug());
        $this->assertEquals('Statamic', $term->get('title'));
    }

    #[Test]
    public function it_imports_a_new_user()
    {
        $this->assertNull(User::findByEmail('john.doe@example.com'));

        ImportItemJob::dispatch(
            config: [
                'destination' => ['type' => 'users'],
                'unique_key' => 'email',
                'mappings' => [
                    'first_name' => ['key' => 'First Name'],
                    'last_name' => ['key' => 'Last Name'],
                    'email' => ['key' => 'Email'],
                ],
            ],
            item: [
                'First Name' => 'John',
                'Last Name' => 'Doe',
                'Email' => 'john.doe@example.com',
            ]
        );

        $user = User::findByEmail('john.doe@example.com');

        $this->assertNotNull($user);
        $this->assertEquals('John', $user->get('first_name'));
        $this->assertEquals('Doe', $user->get('last_name'));
        $this->assertEquals('john.doe@example.com', $user->email());
    }

    #[Test]
    public function it_updates_an_existing_user()
    {
        $user = User::make()->email('john.doe@example.com');
        $user->save();

        ImportItemJob::dispatch(
            config: [
                'destination' => ['type' => 'users'],
                'unique_key' => 'email',
                'mappings' => [
                    'first_name' => ['key' => 'First Name'],
                    'last_name' => ['key' => 'Last Name'],
                    'email' => ['key' => 'Email'],
                ],
            ],
            item: [
                'First Name' => 'John',
                'Last Name' => 'Doe',
                'Email' => 'john.doe@example.com',
            ]
        );

        $user->fresh();

        $this->assertNotNull($user);
        $this->assertEquals('John', $user->get('first_name'));
        $this->assertEquals('Doe', $user->get('last_name'));
        $this->assertEquals('john.doe@example.com', $user->email());
    }
}
