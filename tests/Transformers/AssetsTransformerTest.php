<?php

namespace Statamic\Importer\Tests\Transformers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection;
use Statamic\Importer\Tests\TestCase;
use Statamic\Importer\Transformers\AssetsTransformer;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class AssetsTransformerTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    public $collection;
    public $blueprint;
    public $field;

    public function setUp(): void
    {
        parent::setUp();

        AssetContainer::make('assets')->disk('public')->save();

        $this->collection = tap(Collection::make('pages'))->save();

        $this->blueprint = $this->collection->entryBlueprint();
        $this->blueprint->ensureField('featured_image', ['type' => 'assets', 'max_files' => 1])->save();

        $this->field = $this->blueprint->field('featured_image');
    }

    #[Test]
    public function it_finds_existing_asset_using_url()
    {
        Http::preventStrayRequests();

        Storage::disk('public')->put('2024/10/image.png', 'original');

        $transformer = new AssetsTransformer($this->blueprint, $this->field, [
            'related_field' => 'url',
            'base_url' => 'https://example.com/wp-content/uploads',
        ]);

        $output = $transformer->transform('https://example.com/wp-content/uploads/2024/10/image.png');

        $this->assertEquals('2024/10/image.png', $output);
    }

    #[Test]
    public function it_finds_existing_asset_using_path()
    {
        Http::preventStrayRequests();

        Storage::disk('public')->put('2024/10/image.png', 'original');

        $transformer = new AssetsTransformer($this->blueprint, $this->field, [
            'related_field' => 'path',
        ]);

        $output = $transformer->transform('2024/10/image.png');

        $this->assertEquals('2024/10/image.png', $output);
    }

    #[Test]
    public function it_downloads_new_asset_using_url()
    {
        Http::fake([
            'https://example.com/wp-content/uploads/2024/10/image.png' => Http::response(UploadedFile::fake()->image('image.png')->size(100)->get()),
        ]);

        Storage::disk('public')->assertMissing('2024/10/image.png');

        $transformer = new AssetsTransformer($this->blueprint, $this->field, [
            'related_field' => 'url',
            'base_url' => 'https://example.com/wp-content/uploads',
            'download_when_missing' => true,
        ]);

        $output = $transformer->transform('https://example.com/wp-content/uploads/2024/10/image.png');

        $this->assertEquals('2024/10/image.png', $output);

        Storage::disk('public')->assertExists('2024/10/image.png');
    }

    #[Test]
    public function it_doesnt_download_new_asset_when_download_when_missing_option_is_disabled()
    {
        Http::preventStrayRequests();

        Storage::disk('public')->assertMissing('2024/10/image.png');

        $transformer = new AssetsTransformer($this->blueprint, $this->field, [
            'related_field' => 'url',
            'base_url' => 'https://example.com/wp-content/uploads',
            'download_when_missing' => false,
        ]);

        $output = $transformer->transform('https://example.com/wp-content/uploads/2024/10/image.png');

        $this->assertNull($output);

        Storage::disk('public')->assertMissing('2024/10/image.png');
    }
}
