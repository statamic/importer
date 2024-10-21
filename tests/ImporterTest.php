<?php

namespace Statamic\Importer\Tests;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Importer\Importer;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class ImporterTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    #[Test]
    public function it_can_import_from_csv_files()
    {
        $collection = tap(Collection::make('team'))->save();

        $collection->entryBlueprint()->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        ['handle' => 'first_name', 'field' => ['type' => 'text']],
                        ['handle' => 'last_name', 'field' => ['type' => 'text']],
                        ['handle' => 'email', 'field' => ['type' => 'text']],
                        ['handle' => 'role', 'field' => ['type' => 'text']],
                        ['handle' => 'imported_id', 'field' => ['type' => 'text']],
                    ],
                ],
            ],
        ])->save();

        File::put(storage_path('import.csv'), <<<'CSV'
"ID","First Name","Last Name","Email","Role"
"one","John","Doe","john.doe@example.com","CEO"
CSV
        );

        $this->assertNull(Entry::query()->where('imported_id', 'one')->first());

        Importer::import(
            config: [
                'destination' => ['type' => 'entries', 'collection' => 'team'],
                'unique_key' => 'imported_id',
                'mappings' => [
                    'first_name' => ['key' => 'First Name'],
                    'last_name' => ['key' => 'Last Name'],
                    'email' => ['key' => 'Email'],
                    'role' => ['key' => 'Role'],
                    'imported_id' => ['key' => 'ID'],
                ],
            ],
            path: storage_path('import.csv')
        );

        $entry = Entry::query()->where('imported_id', 'one')->first();

        $this->assertNotNull($entry);
        $this->assertEquals('John', $entry->get('first_name'));
        $this->assertEquals('Doe', $entry->get('last_name'));
        $this->assertEquals('john.doe@example.com', $entry->get('email'));
        $this->assertEquals('CEO', $entry->get('role'));
    }

    #[Test]
    public function it_can_import_from_xml_files()
    {
        $collection = tap(Collection::make('posts')->dated(true))->save();

        $collection->entryBlueprint()->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        ['handle' => 'title', 'field' => ['type' => 'text']],
                        ['handle' => 'date', 'field' => ['type' => 'date']],
                        ['handle' => 'content', 'field' => ['type' => 'text']],
                        ['handle' => 'excerpt', 'field' => ['type' => 'text']],
                        ['handle' => 'imported_id', 'field' => ['type' => 'text']],
                        ['handle' => 'foo', 'field' => ['type' => 'text']],
                        ['handle' => 'bar', 'field' => ['type' => 'text']],
                    ],
                ],
            ], ['handle' => 'date', 'field' => ['type' => 'date']],
        ])->save();

        File::put(storage_path('import.xml'), <<<'XML'
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0"
    xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wp="http://wordpress.org/export/1.2/"
>
    <channel>
        <title>Previous CMS</title>
        <link>https://example.com</link>
		<item>
			<title><![CDATA[Quas corporis ea sit]]></title>
			<pubDate>Mon, 03 Jun 2024 00:00:00 +0000</pubDate>
			<content:encoded><![CDATA[<h2>Inventore autem reprehenderit et</h2>]]></content:encoded>
			<excerpt:encoded><![CDATA[In excepteur ex aliquip laborum velit proident est officia in ex labore do.]]></excerpt:encoded>
			<wp:post_id>one</wp:post_id>
			<wp:postmeta>
				<wp:meta_key><![CDATA[foo]]></wp:meta_key>
				<wp:meta_value><![CDATA[bar]]></wp:meta_value>
			</wp:postmeta>
			<wp:postmeta>
				<wp:meta_key><![CDATA[bar]]></wp:meta_key>
				<wp:meta_value><![CDATA[baz]]></wp:meta_value>
			</wp:postmeta>
		</item>
	</channel>
</rss>
XML
        );

        $this->assertNull(Entry::query()->where('imported_id', 'one')->first());

        Importer::import(
            config: [
                'destination' => ['type' => 'entries', 'collection' => 'posts'],
                'unique_key' => 'imported_id',
                'mappings' => [
                    'title' => ['key' => 'title'],
                    'date' => ['key' => 'pubDate'],
                    'content' => ['key' => 'content:encoded'],
                    'excerpt' => ['key' => 'excerpt:encoded'],
                    'imported_id' => ['key' => 'wp:post_id'],
                    'foo' => ['key' => "wp:postmeta[wp:meta_key='foo']/wp:meta_value"],
                    'bar' => ['key' => "wp:postmeta[wp:meta_key='bar']/wp:meta_value"],
                ],
            ],
            path: storage_path('import.xml')
        );

        $entry = Entry::query()->where('imported_id', 'one')->first();

        $this->assertNotNull($entry);
        $this->assertEquals('Quas corporis ea sit', $entry->get('title'));
        $this->assertEquals('2024-06-03', $entry->date()->format('Y-m-d'));
        $this->assertEquals('<h2>Inventore autem reprehenderit et</h2>', $entry->get('content'));
        $this->assertEquals('In excepteur ex aliquip laborum velit proident est officia in ex labore do.', $entry->get('excerpt'));
        $this->assertEquals('bar', $entry->get('foo'));
        $this->assertEquals('baz', $entry->get('bar'));
    }

    #[Test]
    public function transformer_can_be_registered()
    {
        //        Importer::registerTransformer('foo', FooTransformer::class);
    }
}
