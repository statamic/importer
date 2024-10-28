<?php

namespace Statamic\Importer\Tests\WordPress;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Importer\Tests\TestCase;
use Statamic\Importer\WordPress\Gutenberg;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class GutenbergTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    public $blueprint;

    public function setUp(): void
    {
        parent::setUp();

        $collection = tap(Collection::make('posts'))->save();
        $this->blueprint = $collection->entryBlueprint();

        $this->blueprint->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        ['handle' => 'content', 'field' => ['type' => 'bard', 'container' => 'assets']],
                    ],
                ],
            ],
        ])->save();
    }

    #[Test]
    public function it_transforms_text()
    {
        $output = Gutenberg::toBard(
            config: [],
            blueprint: $this->blueprint,
            field: $this->blueprint->field('content'),
            value: <<<'HTML'
<!-- wp:heading -->
<h2 class="wp-block-heading">Interesting thoughts...</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Enim amet culpa ut nulla elit ex excepteur qui aliqua laborum officia ullamco officia elit mollit <a href="https://statamic.com" data-type="link" data-id="https://statamic.com">Anim sint esse nostrud pro</a>. Lorem ipsum non qui culpa anim reprehenderit.</p>
<!-- /wp:paragraph -->
HTML
        );

        $this->assertEquals([
            [
                'type' => 'heading',
                'attrs' => ['level' => 2, 'textAlign' => 'left'],
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Interesting thoughts...',
                    ],
                ],
            ],
            [
                'type' => 'paragraph',
                'attrs' => ['textAlign' => 'left'],
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Enim amet culpa ut nulla elit ex excepteur qui aliqua laborum officia ullamco officia elit mollit ',
                    ],
                    [
                        'type' => 'text',
                        'text' => 'Anim sint esse nostrud pro',
                        'marks' => [
                            ['type' => 'link', 'attrs' => ['href' => 'https://statamic.com']],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'text' => '. Lorem ipsum non qui culpa anim reprehenderit.',
                    ],
                ],
            ],
        ], $output);
    }

    #[Test]
    public function it_transforms_list_blocks()
    {
        $output = Gutenberg::toBard(
            config: [],
            blueprint: $this->blueprint,
            field: $this->blueprint->field('content'),
            value: <<<'HTML'
<!-- wp:list {"ordered":true} -->
<ol class="wp-block-list"><!-- wp:list-item -->
<li>Ordered One</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Ordered Two</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Ordered Three</li>
<!-- /wp:list-item --></ol>
<!-- /wp:list -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>Unordered One</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Unordered Two</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Unordered Three</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->
HTML
        );

        $this->assertEquals([
            [
                'type' => 'orderedList',
                'content' => [
                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Ordered One']]]]],
                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Ordered Two']]]]],
                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Ordered Three']]]]],
                ],
            ],
            [
                'type' => 'bulletList',
                'content' => [
                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Unordered One']]]]],
                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Unordered Two']]]]],
                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Unordered Three']]]]],
                ],
            ],
        ], $output);
    }

    #[Test]
    public function it_transforms_quotes_blocks()
    {
        $output = Gutenberg::toBard(
            config: [],
            blueprint: $this->blueprint,
            field: $this->blueprint->field('content'),
            value: <<<'HTML'
<!-- wp:quote -->
<blockquote class="wp-block-quote"><!-- wp:paragraph -->
<p>Great achievements are born from collaboration; together, we can turn the impossible into the possible.</p>
<!-- /wp:paragraph --></blockquote>
<!-- /wp:quote -->
HTML
        );

        $this->assertEquals([
            [
                'type' => 'blockquote',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'attrs' => ['textAlign' => 'left'],
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Great achievements are born from collaboration; together, we can turn the impossible into the possible.',
                            ],
                        ],
                    ],
                ],
            ],
        ], $output);
    }

    #[Test]
    public function it_transforms_image_blocks()
    {
        Http::preventStrayRequests();

        AssetContainer::make('assets')->disk('public')->save();
        Storage::disk('public')->put('2024/10/image.png', 'original');

        $output = Gutenberg::toBard(
            config: ['assets_base_url' => 'https://example.com/wp-content/uploads'],
            blueprint: $this->blueprint,
            field: $this->blueprint->field('content'),
            value: <<<'HTML'
<!-- wp:image {"id":41,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="https://example.com/wp-content/uploads/2024/10/image.png" alt="" class="wp-image-41"/></figure>
<!-- /wp:image -->
HTML
        );

        $this->assertEquals([
            [
                'type' => 'paragraph',
                'content' => [
                    [
                        'type' => 'image',
                        'attrs' => [
                            'src' => 'assets::2024/10/image.png',
                            'alt' => null,
                        ],
                    ],
                ],
            ],
        ], $output);
    }

    #[Test]
    public function it_doesnt_transform_image_blocks_without_base_url()
    {
        Http::preventStrayRequests();

        AssetContainer::make('assets')->disk('public')->save();
        Storage::disk('public')->put('2024/10/image.png', 'original');

        $output = Gutenberg::toBard(
            config: [],
            blueprint: $this->blueprint,
            field: $this->blueprint->field('content'),
            value: <<<'HTML'
<!-- wp:image {"id":41,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="https://example.com/wp-content/uploads/2024/10/image.png" alt="" class="wp-image-41"/></figure>
<!-- /wp:image -->
HTML
        );

        $this->assertEquals([], $output);
    }

    #[Test]
    public function it_doesnt_transforms_image_blocks_when_container_is_missing_from_bard_config()
    {
        Http::preventStrayRequests();

        AssetContainer::make('assets')->disk('public')->save();
        Storage::disk('public')->put('2024/10/image.png', 'original');

        $this->blueprint->ensureFieldHasConfig('content', ['type' => 'bard', 'container' => null])->save();

        $output = Gutenberg::toBard(
            config: [],
            blueprint: $this->blueprint,
            field: $this->blueprint->field('content'),
            value: <<<'HTML'
<!-- wp:image {"id":41,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="https://example.com/wp-content/uploads/2024/10/image.png" alt="" class="wp-image-41"/></figure>
<!-- /wp:image -->
HTML
        );

        $this->assertEquals([], $output);
    }

    #[Test]
    public function it_transforms_image_blocks_and_downloads_images_that_dont_exist_in_asset_container()
    {
        Http::fake([
            'https://example.com/wp-content/uploads/2024/10/image.png' => Http::response(UploadedFile::fake()->image('image.png')->size(100)->get()),
        ]);

        AssetContainer::make('assets')->disk('public')->save();

        Storage::disk('public')->assertMissing('2024/10/image.png');

        $output = Gutenberg::toBard(
            config: [
                'assets_base_url' => 'https://example.com/wp-content/uploads',
                'assets_download_when_missing' => true,
            ],
            blueprint: $this->blueprint,
            field: $this->blueprint->field('content'),
            value: <<<'HTML'
<!-- wp:image {"id":41,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="https://example.com/wp-content/uploads/2024/10/image.png" alt="" class="wp-image-41"/></figure>
<!-- /wp:image -->
HTML
        );

        $this->assertEquals([
            [
                'type' => 'paragraph',
                'content' => [
                    [
                        'type' => 'image',
                        'attrs' => [
                            'src' => 'assets::2024/10/image.png',
                            'alt' => null,
                        ],
                    ],
                ],
            ],
        ], $output);

        Storage::disk('public')->assertExists('2024/10/image.png');
    }

    #[Test]
    public function it_transforms_image_blocks_but_doesnt_attempt_to_download_assets_that_dont_exist_in_the_asset_container()
    {
        Http::preventStrayRequests();

        AssetContainer::make('assets')->disk('public')->save();

        Storage::disk('public')->assertMissing('2024/10/image.png');

        $output = Gutenberg::toBard(
            config: [
                'assets_base_url' => 'https://example.com/wp-content/uploads',
                'assets_download_when_missing' => false,
            ],
            blueprint: $this->blueprint,
            field: $this->blueprint->field('content'),
            value: <<<'HTML'
<!-- wp:image {"id":41,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="https://example.com/wp-content/uploads/2024/10/image.png" alt="" class="wp-image-41"/></figure>
<!-- /wp:image -->
HTML
        );

        $this->assertEquals([], $output);

        Storage::disk('public')->assertMissing('2024/10/image.png');
    }

    #[Test]
    public function it_transforms_gallery_blocks()
    {
        Http::preventStrayRequests();
        Str::createRandomStringsUsing(fn () => 'fakeSetId');

        AssetContainer::make('assets')->disk('public')->save();
        Storage::disk('public')->put('2024/10/image.png', 'original');
        Storage::disk('public')->put('2024/09/foo.jpeg', 'original');
        Storage::disk('public')->put('2024/09/bar.gif', 'original');

        $output = Gutenberg::toBard(
            config: ['assets_base_url' => 'https://example.com/wp-content/uploads'],
            blueprint: $this->blueprint,
            field: $this->blueprint->field('content'),
            value: <<<'HTML'
<!-- wp:gallery {"linkTo":"none"} -->
<figure class="wp-block-gallery has-nested-images columns-default is-cropped"><!-- wp:image {"id":47,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="https://example.com/wp-content/uploads/2024/10/image.png" alt="" class="wp-image-47"/><figcaption class="wp-element-caption">This is a caption for image number one.</figcaption></figure>
<!-- /wp:image -->

<!-- wp:image {"id":43,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="https://example.com/wp-content/uploads/2024/09/foo.jpeg" alt="" class="wp-image-43"/></figure>
<!-- /wp:image -->

<!-- wp:image {"id":35,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="https://example.com/wp-content/uploads/2024/09/bar.gif" alt="" class="wp-image-35"/></figure>
<!-- /wp:image -->
<!-- /wp:gallery -->
HTML
        );

        $blueprint = Blueprint::find('collections.posts.post');
        $this->assertSetExists('gallery', $blueprint->field('content'));

        $this->assertEquals([
            [
                'type' => 'set',
                'attrs' => [
                    'id' => 'fakeSetId',
                    'values' => [
                        'type' => 'gallery',
                        'images' => [
                            '2024/10/image.png',
                            '2024/09/foo.jpeg',
                            '2024/09/bar.gif',
                        ],
                    ],
                ],
            ],
        ], $output);
    }

    #[Test]
    public function it_doesnt_transform_gallery_blocks_without_base_url()
    {
        Http::preventStrayRequests();
        Str::createRandomStringsUsing(fn () => 'fakeSetId');

        AssetContainer::make('assets')->disk('public')->save();
        Storage::disk('public')->put('2024/10/image.png', 'original');
        Storage::disk('public')->put('2024/09/foo.jpeg', 'original');
        Storage::disk('public')->put('2024/09/bar.gif', 'original');

        $output = Gutenberg::toBard(
            config: [],
            blueprint: $this->blueprint,
            field: $this->blueprint->field('content'),
            value: <<<'HTML'
<!-- wp:gallery {"linkTo":"none"} -->
<figure class="wp-block-gallery has-nested-images columns-default is-cropped"><!-- wp:image {"id":47,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="https://example.com/wp-content/uploads/2024/10/image.png" alt="" class="wp-image-47"/><figcaption class="wp-element-caption">This is a caption for image number one.</figcaption></figure>
<!-- /wp:image -->

<!-- wp:image {"id":43,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="https://example.com/wp-content/uploads/2024/09/foo.jpeg" alt="" class="wp-image-43"/></figure>
<!-- /wp:image -->

<!-- wp:image {"id":35,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="https://example.com/wp-content/uploads/2024/09/bar.gif" alt="" class="wp-image-35"/></figure>
<!-- /wp:image -->
<!-- /wp:gallery -->
HTML
        );

        $blueprint = Blueprint::find('collections.posts.post');
        $this->assertSetNotExists('gallery', $blueprint->field('content'));

        $this->assertEquals([], $output);
    }

    #[Test]
    public function it_transforms_html_block()
    {
        Str::createRandomStringsUsing(fn () => 'fakeSetId');

        $output = Gutenberg::toBard(
            config: [],
            blueprint: $this->blueprint,
            field: $this->blueprint->field('content'),
            value: <<<'HTML'
<!-- wp:embed {"url":"https://twitter.com.com/statamic/status/1845937136584835529","type":"rich","providerNameSlug":"twitter","responsive":true} -->
<figure class="wp-block-embed is-type-rich is-provider-twitter wp-block-embed-twitter"><div class="wp-block-embed__wrapper">
https://twitter.com.com/statamic/status/1845937136584835529
</div></figure>
<!-- /wp:embed -->
HTML
        );

        $blueprint = Blueprint::find('collections.posts.post');
        $this->assertSetExists('html', $blueprint->field('content'));

        $this->assertEquals([
            [
                'type' => 'set',
                'attrs' => [
                    'id' => 'fakeSetId',
                    'values' => [
                        'type' => 'html',
                        'html' => [
                            'code' => '
<figure class="wp-block-embed is-type-rich is-provider-twitter wp-block-embed-twitter"><div class="wp-block-embed__wrapper">
https://twitter.com.com/statamic/status/1845937136584835529
</div></figure>
',
                            'mode' => 'htmlmixed',
                        ],
                    ],
                ],
            ],
        ], $output);
    }

    #[Test]
    public function it_transforms_video_block()
    {
        Str::createRandomStringsUsing(fn () => 'fakeSetId');

        $output = Gutenberg::toBard(
            config: [],
            blueprint: $this->blueprint,
            field: $this->blueprint->field('content'),
            value: <<<'HTML'
<!-- wp:video -->
<figure class="wp-block-video"><video controls src="https://example.com/video.mp4"></video></figure>
<!-- /wp:video -->
HTML
        );

        $blueprint = Blueprint::find('collections.posts.post');
        $this->assertSetExists('video', $blueprint->field('content'));

        $this->assertEquals([
            [
                'type' => 'set',
                'attrs' => [
                    'id' => 'fakeSetId',
                    'values' => [
                        'type' => 'video',
                        'video' => 'https://example.com/video.mp4',
                    ],
                ],
            ],
        ], $output);
    }

    #[Test]
    public function it_transforms_video_block_into_html_embed_when_no_video_element_is_present()
    {
        Str::createRandomStringsUsing(fn () => 'fakeSetId');

        $output = Gutenberg::toBard(
            config: [],
            blueprint: $this->blueprint,
            field: $this->blueprint->field('content'),
            value: <<<'HTML'
<!-- wp:video {"guid":"9OB1qME8","id":1420,"src":"https://videos.files.wordpress.com/12345abc/2_mp4_hd.mp4","videoPressClassNames":"wp-block-embed is-type-video is-provider-videopress wp-embed-aspect-9-16 wp-has-aspect-ratio"} -->
<figure class="wp-block-video wp-block-embed is-type-video is-provider-videopress wp-embed-aspect-9-16 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">
https://videopress.com/v/12345abc?preloadContent=metadata
</div></figure>
<!-- /wp:video -->
HTML
        );

        $blueprint = Blueprint::find('collections.posts.post');
        $this->assertSetExists('video', $blueprint->field('content'));

        $this->assertEquals([
            [
                'type' => 'set',
                'attrs' => [
                    'id' => 'fakeSetId',
                    'values' => [
                        'type' => 'html',
                        'html' => [
                            'code' => '
<figure class="wp-block-video wp-block-embed is-type-video is-provider-videopress wp-embed-aspect-9-16 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">
https://videopress.com/v/12345abc?preloadContent=metadata
</div></figure>
',
                            'mode' => 'htmlmixed',
                        ],
                    ],
                ],
            ],
        ], $output);
    }

    #[Test]
    public function it_transforms_embed_block_with_video_from_youtube()
    {
        Str::createRandomStringsUsing(fn () => 'fakeSetId');

        $output = Gutenberg::toBard(
            config: [],
            blueprint: $this->blueprint,
            field: $this->blueprint->field('content'),
            value: <<<HTML
<!-- wp:embed {"url":"https://www.youtube.com/watch?v=dQw4w9WgXcQ\u0026pp=ygUKcmljayBydG9sbA%3D%3D","type":"video","providerNameSlug":"youtube","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} -->
<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">
https://www.youtube.com/watch?v=dQw4w9WgXcQ&amp;pp=ygUKcmljayBydG9sbA%3D%3D
</div></figure>
<!-- /wp:embed -->
HTML
        );

        $blueprint = Blueprint::find('collections.posts.post');

        $this->assertSetExists('video', $blueprint->field('content'));

        $this->assertEquals([
            [
                'type' => 'set',
                'attrs' => [
                    'id' => 'fakeSetId',
                    'values' => [
                        'type' => 'video',
                        'video' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&pp=ygUKcmljayBydG9sbA%3D%3D',
                    ],
                ],
            ],
        ], $output);
    }

    #[Test]
    public function it_transforms_code_blocks()
    {
        Str::createRandomStringsUsing(fn () => 'fakeSetId');

        $output = Gutenberg::toBard(
            config: [],
            blueprint: $this->blueprint,
            field: $this->blueprint->field('content'),
            value: <<<'HTML'
<!-- wp:code -->
<pre class="wp-block-code"><code>return 'hello world';</code></pre>
<!-- /wp:code -->
HTML
        );

        $blueprint = Blueprint::find('collections.posts.post');
        $this->assertSetExists('code', $blueprint->field('content'));

        $this->assertEquals([
            [
                'type' => 'set',
                'attrs' => [
                    'id' => 'fakeSetId',
                    'values' => [
                        'type' => 'code',
                        'code' => [
                            'code' => "return 'hello world';",
                        ],
                    ],
                ],
            ],
        ], $output);
    }

    #[Test]
    public function it_transforms_button_blocks()
    {
        Str::createRandomStringsUsing(fn () => 'fakeSetId');

        $output = Gutenberg::toBard(
            config: [],
            blueprint: $this->blueprint,
            field: $this->blueprint->field('content'),
            value: <<<'HTML'
<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://example.com/foo/" target="_blank" rel="noreferrer noopener nofollow">Foo</a></div>
<!-- /wp:button -->

<!-- wp:button -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="https://statamic.com">Bar</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
HTML
        );

        $blueprint = Blueprint::find('collections.posts.post');
        $this->assertSetExists('buttons', $blueprint->field('content'));

        $this->assertEquals([
            [
                'type' => 'set',
                'attrs' => [
                    'id' => 'fakeSetId',
                    'values' => [
                        'type' => 'buttons',
                        'buttons' => [
                            [
                                'id' => 'fakeSetId',
                                'label' => 'Foo',
                                'url' => 'https://example.com/foo/',
                                'open_in_new_tab' => true,
                                'mark_as_nofollow' => true,
                            ],
                            [
                                'id' => 'fakeSetId',
                                'label' => 'Bar',
                                'url' => 'https://statamic.com',
                                'open_in_new_tab' => false,
                                'mark_as_nofollow' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ], $output);
    }

    #[Test]
    public function it_transforms_detail_blocks()
    {
        Str::createRandomStringsUsing(fn () => 'fakeSetId');

        $output = Gutenberg::toBard(
            config: [],
            blueprint: $this->blueprint,
            field: $this->blueprint->field('content'),
            value: <<<'HTML'
<!-- wp:details -->
<details class="wp-block-details"><summary>Aute duis reprehenderit aliquip cillum.</summary><!-- wp:paragraph {"placeholder":"Type / to add a hidden block"} -->
<p>Anim consectetur dolor minim. Anim Lorem reprehenderit duis voluptate amet elit eu laboris dolor consequat sunt sunt velit proident ex. Quis sit est reprehenderit.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->
HTML
        );

        $blueprint = Blueprint::find('collections.posts.post');
        $this->assertSetExists('details', $blueprint->field('content'));

        $this->assertEquals([
            [
                'type' => 'set',
                'attrs' => [
                    'id' => 'fakeSetId',
                    'values' => [
                        'type' => 'details',
                        'summary' => 'Aute duis reprehenderit aliquip cillum.',
                        'content' => [
                            [
                                'type' => 'paragraph',
                                'attrs' => ['textAlign' => 'left'],
                                'content' => [
                                    [
                                        'type' => 'text',
                                        'text' => 'Anim consectetur dolor minim. Anim Lorem reprehenderit duis voluptate amet elit eu laboris dolor consequat sunt sunt velit proident ex. Quis sit est reprehenderit.',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $output);
    }

    #[Test]
    public function it_transforms_separator_blocks()
    {
        $output = Gutenberg::toBard(
            config: [],
            blueprint: $this->blueprint,
            field: $this->blueprint->field('content'),
            value: <<<'HTML'
<!-- wp:separator -->
<hr class="wp-block-separator has-alpha-channel-opacity"/>
<!-- /wp:separator -->
HTML
        );

        $this->assertEquals([
            ['type' => 'horizontalRule'],
        ], $output);
    }

    #[Test]
    public function it_transforms_spacer_blocks()
    {
        Str::createRandomStringsUsing(fn () => 'fakeSetId');

        $output = Gutenberg::toBard(
            config: [],
            blueprint: $this->blueprint,
            field: $this->blueprint->field('content'),
            value: <<<'HTML'
<!-- wp:spacer -->
<div style="height:100px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->
HTML
        );

        $blueprint = Blueprint::find('collections.posts.post');
        $this->assertSetExists('spacer', $blueprint->field('content'));

        $this->assertEquals([
            [
                'type' => 'set',
                'attrs' => [
                    'id' => 'fakeSetId',
                    'values' => [
                        'type' => 'spacer',
                    ],
                ],
            ],
        ], $output);
    }

    #[Test]
    public function it_returns_hook_output()
    {
        Gutenberg::hook('core/paragraph', function ($node) {
            return [
                'type' => 'paragraph',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Hello, world!',
                    ],
                ],
            ];
        });

        $output = Gutenberg::toBard(
            config: [],
            blueprint: $this->blueprint,
            field: $this->blueprint->field('content'),
            value: <<<'HTML'
<!-- wp:paragraph -->
<p>As long as the hook is working, whatever we have here won't be returned.</p>
<!-- /wp:paragraph -->
HTML
        );

        $this->assertEquals([
            [
                'type' => 'paragraph',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Hello, world!',
                    ],
                ],
            ],
        ], $output);
    }

    protected function assertSetExists(string $handle, $field): void
    {
        $setExists = collect($field->get('sets', []))->contains(
            fn (array $section) => collect($section['sets'] ?? [])->contains(fn (array $setConfig, string $setHandle) => $setHandle === $handle)
        );

        self::assertThat($setExists, self::isTrue());
    }

    protected function assertSetNotExists(string $handle, $field): void
    {
        $setExists = collect($field->get('sets', []))->contains(
            fn (array $section) => collect($section['sets'] ?? [])->contains(fn (array $setConfig, string $setHandle) => $setHandle === $handle)
        );

        self::assertThat(! $setExists, self::isTrue());
    }
}
