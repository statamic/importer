<?php

namespace Statamic\Importer\Tests\Transformers;

use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection;
use Statamic\Importer\Tests\TestCase;
use Statamic\Importer\Transformers\BardTransformer;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class BardTransformerTest extends TestCase
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
        $this->blueprint->ensureField('content', ['type' => 'bard', 'container' => 'assets'])->save();

        $this->field = $this->blueprint->field('content');
    }

    #[Test]
    public function it_converts_html_to_prosemirror()
    {
        $transformer = new BardTransformer($this->blueprint, $this->field, []);

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
    public function it_handles_images()
    {
        AssetContainer::make('assets')->disk('public')->save();
        Storage::disk('public')->put('2024/10/image.png', 'original');

        $transformer = new BardTransformer($this->blueprint, $this->field, [
            'assets_base_url' => 'https://example.com/wp-content/uploads',
        ]);

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
    public function it_doesnt_handles_images_without_base_url()
    {
        AssetContainer::make('assets')->disk('public')->save();
        Storage::disk('public')->put('2024/10/image.png', 'original');

        $transformer = new BardTransformer($this->blueprint, $this->field, []);

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
}
