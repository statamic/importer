<?php

namespace Statamic\Importer\Tests\Transformers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection;
use Statamic\Facades\Fieldset;
use Statamic\Importer\Facades\Import;
use Statamic\Importer\Tests\TestCase;
use Statamic\Importer\Transformers\BardTransformer;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class BardTransformerTest extends TestCase
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
        $this->blueprint->ensureField('content', ['type' => 'bard', 'container' => 'assets'])->save();

        $this->field = $this->blueprint->field('content');

        $this->import = Import::make();
    }

    #[Test]
    public function it_converts_html_to_prosemirror()
    {
        $transformer = new BardTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->field,
            config: []
        );

        $output = $transformer->transform(<<<'HTML'
<h2>Summary</h2>
<ol><li>One</li><li>Two</li><li>Three</li><li>Four</li></ol>
<h2>Blah</h2>
<p>Nam voluptatem rem molestiae cumque doloremque. <strong>Saepe animi deserunt</strong> Maxime iam et inventore. ipsam in dignissimos qui occaecati.</p>
<p>Consectetur incididunt nulla cupre quis qui alident aliquip ipsum ad.</p>
<ul><li>Ein</li><li>Zwei</li></ul>
<h5>Omnis porro numquam praesentium totam blanditiis voluptatem</h5>
<ol><li>Quia et nihil et occaecati aliquam</li><li>Omnis natus fugit dolor</li><li>Provident et qui sequi aut</li><li>Eos blanditiis eligendi sit et sapiente</li></ol>
<p>Consequuntur <a title="Quia quod et." href="http://example.com">aspernatur. Hic</a> voluptas et exercitationem.</p>
<!--more-->
<hr>
<h2>Blah blah</h2>
HTML);

        // We don't need to check every single node. Just a few to make sure it's working.
        $this->assertContains([
            'type' => 'heading',
            'attrs' => ['level' => 2, 'textAlign' => 'left'],
            'content' => [['type' => 'text', 'text' => 'Summary']],
        ], $output);

        $this->assertContains(['type' => 'orderedList', 'content' => [
            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'One']]]]],
            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Two']]]]],
            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Three']]]]],
            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Four']]]]],
        ]], $output);

        $this->assertContains(['type' => 'paragraph', 'attrs' => ['textAlign' => 'left'], 'content' => [
            ['type' => 'text', 'text' => 'Nam voluptatem rem molestiae cumque doloremque. '],
            ['type' => 'text', 'text' => 'Saepe animi deserunt', 'marks' => [['type' => 'bold']]],
            ['type' => 'text', 'text' => ' Maxime iam et inventore. ipsam in dignissimos qui occaecati.'],
        ]], $output);
    }

    #[Test]
    public function it_passes_value_through_wpautop()
    {
        $transformer = new BardTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->field,
            config: [
                'wp_auto_p' => true,
            ]
        );

        $output = $transformer->transform(<<<'HTML'
<strong>Foo <a href="https://example.com">Bar</a> Baz.</strong>
<h2>Heading</h2>
One. Two. Three. Four.

<img src="/images/pizza.jpg" alt="Picture of pizza" width="100" height="100" />

Ein.
Zwei.

Drei.

<a class="btn btn-primary" href="https://statamic.com">Statamic</a>
<h2>Heading 2</h2>
Blah. <a href="https://example.com">Link</a> blah.
HTML);

        $this->assertEquals([
            ['type' => 'paragraph', 'attrs' => ['textAlign' => 'left'], 'content' => [
                ['type' => 'text', 'text' => 'Foo ', 'marks' => [['type' => 'bold']]],
                ['type' => 'text', 'text' => 'Bar', 'marks' => [['type' => 'bold'], ['type' => 'link', 'attrs' => ['href' => 'https://example.com']]]],
                ['type' => 'text', 'text' => ' Baz.', 'marks' => [['type' => 'bold']]],
            ]],
            ['type' => 'heading', 'attrs' => ['level' => 2, 'textAlign' => 'left'], 'content' => [['type' => 'text', 'text' => 'Heading']]],
            ['type' => 'paragraph', 'attrs' => ['textAlign' => 'left'], 'content' => [
                ['type' => 'text', 'text' => 'One. Two. Three. Four.'],
            ]],
            ['type' => 'paragraph', 'attrs' => ['textAlign' => 'left'], 'content' => [
                ['type' => 'image', 'attrs' => ['src' => '/images/pizza.jpg']],
            ]],
            ['type' => 'paragraph', 'attrs' => ['textAlign' => 'left'], 'content' => [
                ['type' => 'text', 'text' => 'Ein.'],
                ['type' => 'hardBreak'],
                ['type' => 'text', 'text' => 'Zwei.'],
            ]],
            ['type' => 'paragraph', 'attrs' => ['textAlign' => 'left'], 'content' => [
                ['type' => 'text', 'text' => 'Drei.'],
            ]],
            ['type' => 'paragraph', 'attrs' => ['textAlign' => 'left'], 'content' => [
                ['type' => 'text', 'text' => 'Statamic', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://statamic.com']]]],
            ]],
            ['type' => 'heading', 'attrs' => ['level' => 2, 'textAlign' => 'left'], 'content' => [['type' => 'text', 'text' => 'Heading 2']]],
            ['type' => 'paragraph', 'attrs' => ['textAlign' => 'left'], 'content' => [
                ['type' => 'text', 'text' => 'Blah. '],
                ['type' => 'text', 'text' => 'Link', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.com']]]],
                ['type' => 'text', 'text' => ' blah.'],
            ]],
        ], $output);
    }

    #[Test]
    public function it_handles_images()
    {
        AssetContainer::make('assets')->disk('public')->save();
        Storage::disk('public')->put('2024/10/image.png', 'original');

        $transformer = new BardTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->field,
            config: [
                'assets_base_url' => 'https://example.com/wp-content/uploads',
            ]
        );

        $output = $transformer->transform(<<<'HTML'
<p>Nam voluptatem rem molestiae cumque doloremque. <strong>Saepe animi deserunt</strong> Maxime iam et inventore. ipsam in dignissimos qui occaecati.</p>
<img src="https://example.com/wp-content/uploads/2024/10/image.png" />
HTML);

        $this->assertEquals([
            [
                'type' => 'paragraph',
                'attrs' => ['textAlign' => 'left'],
                'content' => [
                    ['type' => 'text', 'text' => 'Nam voluptatem rem molestiae cumque doloremque. '],
                    ['type' => 'text', 'text' => 'Saepe animi deserunt', 'marks' => [['type' => 'bold']]],
                    ['type' => 'text', 'text' => ' Maxime iam et inventore. ipsam in dignissimos qui occaecati.'],
                ],
            ],
            [
                'type' => 'image',
                'attrs' => [
                    'src' => 'assets::2024/10/image.png',
                ],
            ],
        ], $output);
    }

    #[Test]
    public function it_handles_nested_images()
    {
        AssetContainer::make('assets')->disk('public')->save();
        Storage::disk('public')->put('2024/10/image.png', 'original');

        $transformer = new BardTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->field,
            config: [
                'assets_base_url' => 'https://example.com/wp-content/uploads',
            ]
        );

        $output = $transformer->transform(<<<'HTML'
<p>Nam voluptatem rem molestiae cumque doloremque. <strong>Saepe animi deserunt</strong> Maxime iam et inventore. ipsam in dignissimos qui occaecati.</p>
<p><img src="https://example.com/wp-content/uploads/2024/10/image.png" /></p>
HTML);

        $this->assertEquals([
            [
                'type' => 'paragraph',
                'attrs' => ['textAlign' => 'left'],
                'content' => [
                    ['type' => 'text', 'text' => 'Nam voluptatem rem molestiae cumque doloremque. '],
                    ['type' => 'text', 'text' => 'Saepe animi deserunt', 'marks' => [['type' => 'bold']]],
                    ['type' => 'text', 'text' => ' Maxime iam et inventore. ipsam in dignissimos qui occaecati.'],
                ],
            ],
            [
                'type' => 'paragraph',
                'attrs' => ['textAlign' => 'left'],
                'content' => [
                    [
                        'type' => 'image',
                        'attrs' => [
                            'src' => 'assets::2024/10/image.png',
                        ],
                    ],
                ],
            ],
        ], $output);
    }

    #[Test]
    public function it_doesnt_handles_images_without_base_url()
    {
        AssetContainer::make('assets')->disk('public')->save();
        Storage::disk('public')->put('2024/10/image.png', 'original');

        $transformer = new BardTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->field,
            config: []
        );

        $output = $transformer->transform(<<<'HTML'
<p>Nam voluptatem rem molestiae cumque doloremque. <strong>Saepe animi deserunt</strong> Maxime iam et inventore. ipsam in dignissimos qui occaecati.</p>
<img src="https://example.com/wp-content/uploads/2024/10/image.png" />
HTML);

        $this->assertEquals([
            [
                'type' => 'paragraph',
                'attrs' => ['textAlign' => 'left'],
                'content' => [
                    ['type' => 'text', 'text' => 'Nam voluptatem rem molestiae cumque doloremque. '],
                    ['type' => 'text', 'text' => 'Saepe animi deserunt', 'marks' => [['type' => 'bold']]],
                    ['type' => 'text', 'text' => ' Maxime iam et inventore. ipsam in dignissimos qui occaecati.'],
                ],
            ],
            [
                'type' => 'image',
                'attrs' => [
                    'src' => 'https://example.com/wp-content/uploads/2024/10/image.png',
                ],
            ],
        ], $output);
    }

    #[Test]
    public function it_downloads_images()
    {
        AssetContainer::make('assets')->disk('public')->save();

        Http::fake([
            'https://example.com/wp-content/uploads/2024/10/image.png' => Http::response(UploadedFile::fake()->image('image.png')->size(100)->get()),
        ]);

        Storage::disk('public')->assertMissing('2024/10/image.png');

        $transformer = new BardTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->field,
            config: [
                'assets_base_url' => 'https://example.com/wp-content/uploads',
                'assets_download_when_missing' => true,
            ]
        );

        $output = $transformer->transform(<<<'HTML'
<p>Nam voluptatem rem molestiae cumque doloremque. <strong>Saepe animi deserunt</strong> Maxime iam et inventore. ipsam in dignissimos qui occaecati.</p>
<img src="https://example.com/wp-content/uploads/2024/10/image.png" />
HTML);

        $this->assertEquals([
            [
                'type' => 'paragraph',
                'attrs' => ['textAlign' => 'left'],
                'content' => [
                    ['type' => 'text', 'text' => 'Nam voluptatem rem molestiae cumque doloremque. '],
                    ['type' => 'text', 'text' => 'Saepe animi deserunt', 'marks' => [['type' => 'bold']]],
                    ['type' => 'text', 'text' => ' Maxime iam et inventore. ipsam in dignissimos qui occaecati.'],
                ],
            ],
            [
                'type' => 'image',
                'attrs' => [
                    'src' => 'assets::2024/10/image.png',
                ],
            ],
        ], $output);

        Storage::disk('public')->assertExists('2024/10/image.png');
    }

    #[Test]
    public function it_downloads_images_and_stores_them_in_configured_folder()
    {
        AssetContainer::make('assets')->disk('public')->save();

        Http::fake([
            'https://example.com/wp-content/uploads/2024/10/image.png' => Http::response(UploadedFile::fake()->image('image.png')->size(100)->get()),
        ]);

        Storage::disk('public')->assertMissing('2024/10/image.png');

        $transformer = new BardTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->field,
            config: [
                'assets_base_url' => 'https://example.com/wp-content/uploads',
                'assets_download_when_missing' => true,
                'assets_folder' => 'custom-folder',
            ]
        );

        $output = $transformer->transform(<<<'HTML'
<p>Nam voluptatem rem molestiae cumque doloremque. <strong>Saepe animi deserunt</strong> Maxime iam et inventore. ipsam in dignissimos qui occaecati.</p>
<img src="https://example.com/wp-content/uploads/2024/10/image.png" />
HTML);

        $this->assertEquals([
            [
                'type' => 'paragraph',
                'attrs' => ['textAlign' => 'left'],
                'content' => [
                    ['type' => 'text', 'text' => 'Nam voluptatem rem molestiae cumque doloremque. '],
                    ['type' => 'text', 'text' => 'Saepe animi deserunt', 'marks' => [['type' => 'bold']]],
                    ['type' => 'text', 'text' => ' Maxime iam et inventore. ipsam in dignissimos qui occaecati.'],
                ],
            ],
            [
                'type' => 'image',
                'attrs' => [
                    'src' => 'assets::custom-folder/image.png',
                ],
            ],
        ], $output);

        Storage::disk('public')->assertExists('custom-folder/image.png');
    }

    #[Test]
    public function it_enables_buttons_on_bard_field()
    {
        $transformer = new BardTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->field,
            config: []
        );

        $transformer->transform('<h2 style="text-align: center;"><strong>Hello</strong> <em>world</em>!</h2>');

        $blueprint = $this->collection->entryBlueprint();

        $this->assertEquals([
            'h2',
            'aligncenter',
            'bold',
            'italic',
        ], $blueprint->field('content')->get('buttons'));
    }

    #[Test]
    public function it_enables_buttons_on_imported_bard_field()
    {
        Fieldset::make('content_stuff')->setContents(['fields' => [
            ['handle' => 'bard_field', 'field' => ['type' => 'bard']],
        ]])->save();

        $blueprint = $this->collection->entryBlueprint();

        $this->blueprint->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        ['handle' => 'bard_field', 'field' => 'content_stuff.bard_field'],
                    ],
                ],
            ],
        ])->save();

        $transformer = new BardTransformer(
            import: $this->import,
            blueprint: $blueprint,
            field: $blueprint->field('bard_field'),
            config: []
        );

        $transformer->transform('<h2 style="text-align: center;"><strong>Hello</strong> <em>world</em>!</h2>');

        $fieldset = Fieldset::find('content_stuff');

        $this->assertEquals([
            'h2',
            'aligncenter',
            'bold',
            'italic',
        ], $fieldset->field('bard_field')->get('buttons'));
    }

    #[Test]
    public function it_enables_buttons_on_imported_bard_field_without_prefix()
    {
        Fieldset::make('content_stuff')->setContents(['fields' => [
            ['handle' => 'bard_field', 'field' => ['type' => 'bard']],
        ]])->save();

        $blueprint = $this->collection->entryBlueprint();

        $this->blueprint->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        ['import' => 'content_stuff'],
                    ],
                ],
            ],
        ])->save();

        $transformer = new BardTransformer(
            import: $this->import,
            blueprint: $blueprint,
            field: $blueprint->field('bard_field'),
            config: []
        );

        $transformer->transform('<h2 style="text-align: center;"><strong>Hello</strong> <em>world</em>!</h2>');

        $fieldset = Fieldset::find('content_stuff');

        $this->assertEquals([
            'h2',
            'aligncenter',
            'bold',
            'italic',
        ], $fieldset->field('bard_field')->get('buttons'));
    }

    #[Test]
    public function it_enables_buttons_on_imported_bard_field_with_prefix()
    {
        Fieldset::make('content_stuff')->setContents(['fields' => [
            ['handle' => 'bard_field', 'field' => ['type' => 'bard']],
        ]])->save();

        $blueprint = $this->collection->entryBlueprint();

        $this->blueprint->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        ['import' => 'content_stuff', 'prefix' => 'resources_'],
                    ],
                ],
            ],
        ])->save();

        $transformer = new BardTransformer(
            import: $this->import,
            blueprint: $blueprint,
            field: $blueprint->field('resources_bard_field'),
            config: []
        );

        $transformer->transform('<h2 style="text-align: center;"><strong>Hello</strong> <em>world</em>!</h2>');

        $fieldset = Fieldset::find('content_stuff');

        $this->assertEquals([
            'h2',
            'aligncenter',
            'bold',
            'italic',
        ], $fieldset->field('bard_field')->get('buttons'));
    }
}
