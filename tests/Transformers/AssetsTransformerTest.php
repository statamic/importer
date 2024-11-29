<?php

namespace Statamic\Importer\Tests\Transformers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection;
use Statamic\Importer\Facades\Import;
use Statamic\Importer\Tests\TestCase;
use Statamic\Importer\Transformers\AssetsTransformer;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class AssetsTransformerTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    public $assetContainer;
    public $collection;
    public $blueprint;
    public $field;
    public $import;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assetContainer = tap(AssetContainer::make('assets')->disk('public'))->save();

        $this->collection = tap(Collection::make('pages'))->save();

        $this->blueprint = $this->collection->entryBlueprint();
        $this->blueprint->ensureField('featured_image', ['type' => 'assets', 'max_files' => 1])->save();

        $this->field = $this->blueprint->field('featured_image');

        $this->import = Import::make();
    }

    #[Test]
    public function it_finds_existing_asset_using_url()
    {
        Http::preventStrayRequests();

        Storage::disk('public')->put('2024/10/image.png', 'original');

        $transformer = new AssetsTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->field,
            config: [
                'related_field' => 'url',
                'base_url' => 'https://example.com/wp-content/uploads',
            ]
        );

        $output = $transformer->transform('https://example.com/wp-content/uploads/2024/10/image.png');

        $this->assertEquals('2024/10/image.png', $output);
    }

    #[Test]
    public function it_finds_existing_asset_using_path()
    {
        Http::preventStrayRequests();

        Storage::disk('public')->put('2024/10/image.png', 'original');

        $transformer = new AssetsTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->field,
            config: [
                'related_field' => 'path',
            ]
        );

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

        $transformer = new AssetsTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->field,
            config: [
                'related_field' => 'url',
                'base_url' => 'https://example.com/wp-content/uploads',
                'download_when_missing' => true,
            ]
        );

        $output = $transformer->transform('https://example.com/wp-content/uploads/2024/10/image.png');

        $this->assertEquals('2024/10/image.png', $output);

        Storage::disk('public')->assertExists('2024/10/image.png');
    }

    #[Test]
    public function it_downloads_new_asset_using_url_and_stores_it_in_configured_folder()
    {
        Http::fake([
            'https://example.com/wp-content/uploads/2024/10/image.png' => Http::response(UploadedFile::fake()->image('image.png')->size(100)->get()),
        ]);

        Storage::disk('public')->assertMissing('2024/10/image.png');

        $transformer = new AssetsTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->field,
            config: [
                'related_field' => 'url',
                'base_url' => 'https://example.com/wp-content/uploads',
                'download_when_missing' => true,
                'folder' => 'custom-folder',
            ]
        );

        $output = $transformer->transform('https://example.com/wp-content/uploads/2024/10/image.png');

        $this->assertEquals('custom-folder/image.png', $output);

        Storage::disk('public')->assertExists('custom-folder/image.png');
    }

    #[Test]
    public function it_doesnt_download_new_asset_when_download_when_missing_option_is_disabled()
    {
        Http::preventStrayRequests();

        Storage::disk('public')->assertMissing('2024/10/image.png');

        $transformer = new AssetsTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->field,
            config: [
                'related_field' => 'url',
                'base_url' => 'https://example.com/wp-content/uploads',
                'download_when_missing' => false,
            ]
        );

        $output = $transformer->transform('https://example.com/wp-content/uploads/2024/10/image.png');

        $this->assertNull($output);

        Storage::disk('public')->assertMissing('2024/10/image.png');
    }

    #[Test]
    public function it_sets_alt_text_on_existing_asset()
    {
        Http::preventStrayRequests();

        Storage::disk('public')->put('2024/10/image.png', 'original');

        $transformer = new AssetsTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->field,
            config: [
                'related_field' => 'url',
                'base_url' => 'https://example.com/wp-content/uploads',
                'alt' => 'Image Alt Text',
            ],
            item: [
                'Image Alt Text' => 'A photo taken by someone.',
            ],
        );

        $asset = $this->assetContainer->asset('2024/10/image.png');
        $this->assertNull($asset->get('alt'));

        $output = $transformer->transform('https://example.com/wp-content/uploads/2024/10/image.png');
        $this->assertEquals('2024/10/image.png', $output);

        $asset = $this->assetContainer->asset('2024/10/image.png');
        $this->assertEquals('A photo taken by someone.', $asset->get('alt'));
    }

    #[Test]
    public function it_sets_alt_text_on_downloaded_asset()
    {
        Http::fake([
            'https://example.com/wp-content/uploads/2024/10/image.png' => Http::response(UploadedFile::fake()->image('image.png')->size(100)->get()),
        ]);

        Storage::disk('public')->assertMissing('2024/10/image.png');

        $transformer = new AssetsTransformer(
            import: $this->import,
            blueprint: $this->blueprint,
            field: $this->field,
            config: [
                'related_field' => 'url',
                'base_url' => 'https://example.com/wp-content/uploads',
                'download_when_missing' => true,
                'alt' => 'Image Alt Text',
            ],
            item: [
                'Image Alt Text' => 'A photo taken by someone.',
            ],
        );

        $this->assertNull($this->assetContainer->asset('2024/10/image.png'));

        $output = $transformer->transform('https://example.com/wp-content/uploads/2024/10/image.png');

        $this->assertEquals('2024/10/image.png', $output);

        Storage::disk('public')->assertExists('2024/10/image.png');

        $asset = $this->assetContainer->asset('2024/10/image.png');
        $this->assertEquals('A photo taken by someone.', $asset->get('alt'));
    }
}
