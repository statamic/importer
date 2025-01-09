## Installation

> **Note:**
> Before installing the importer addon, please ensure your project has a database configured. This importer uses a database to keep track of import progress. If you created your site using the [Statamic CLI](https://github.com/statamic/cli), a SQLite database will have been setup for you. You can confirm by running `php artisan migrate`.

1. You can install the Importer addon via Composer:

   ```bash
   composer require statamic/importer
   ```

2. You can find the Importer addon in the Control Panel under `Utilities > Importer`.


## Usage

### Preparation
Before importing, you will need to do some preparation:

* Ensure you have either a CSV or XML file to import.
  * You will need to produce separate files for each type of content you wish to import. For example: one file for Page entries, one for Blog entries, another for Users, etc.
  * When you're uploading a CSV, the CSV needs to have a header row.
  * Some fields allow importing multiple values. In this case, values should be separated by a pipe (`|`).
  * When you're coming from WordPress, please see the [WordPress](#wordpress) section below.
* Create the necessary collections and taxonomies in Statamic.
* Configure the necessary blueprints for those collections and taxonomies.
  * It might be a good idea to add a `old_id` field to your blueprints in order to store the ID of content from the old system. You can remove this field once you're done with the import.
  * If you're importing any Bard fields, you should ensure the Bard field has an asset container configured, otherwise assets won't be imported.

### Importing
1. Navigate to the Importer utility in the Control Panel.
2. Give your import a name, and upload the file you wish to import. You'll also be asked which type of content you're importing (entries, taxonomy terms, or users) and if you want to create new items or update existing items.
3. You can then map fields from your blueprint to fields/columns in your file.
    * Depending on the fieldtype, some fields may have additional options, like "Related Key" or "Create when missing". You can read more about these below.
    * Mapping is disabled for some fieldtypes, like the [Replicator fieldtype](https://statamic.dev/fieldtypes/replicator#content). If you wish to import these fields, you will need to build a [custom transformer](#transformers).
    * When you're importing taxonomy terms into a non-default site (eg. not the _first_ site you created) and the slugs differ between sites, you can map the "Slug in Default Site" field to the slug of the term in the default site in order for the importer to match up the terms correctly.
4. If you're importing entries, you will also need to specify a "Unique Field". This field will be used to determine if an entry already exists in Statamic.
5. Then, run the import and watch the magic happen! ✨

You can run the importer as many times as you like as you tweak the mappings. It'll update existing content and create new content as needed.

#### Queueing
If you're importing a lot of content or downloading a lot of assets, you may want to consider running a queue worker to handle the import in the background.

Assuming you have Redis installed, you can update the `QUEUE_CONNECTION` in your `.env` file to `redis` and then run:

```bash
php artisan queue:work
```

To find out more about the available queue drivers, please review the [Laravel documentation](https://laravel.com/docs/queues#driver-prerequisites).

### Assets
When you're configuring mappings for an Assets field, or a Bard field, a few additional config options will be shown:

* **Related Field**
    * This field is used to determine how assets are referenced in the file you're importing. For example: if your import references assets by their URL, you should set this to "URL".
* **Base URL**
    * Shown when "Related Field" is set to "URL", the base URL is the start of the URL leading up to the root of the asset container. The importer uses this to strip the base URL from the asset URL to determine if the asset already exists in Statamic.
    * For example: if your asset URLs look like `https://example.com/uploads/2024/10/image.jpg`, the base URL would be `https://example.com/uploads/`.
* **Download when missing?**
    * By default, when the importer comes across an asset it can't find in Statamic, it will skip it.
    * However, if you wish, the importer can download any missing assets for you into the configured asset container.
* **Process downloaded images?**
    * Determine whether downloaded images should be processed using the asset container's [source preset](https://statamic.dev/image-manipulation#process-source-images).
* **Folder**
    * By default, downloaded assets will use same folder structure as the original URL. If you'd like to download them into a specific folder, you can select one here.
* **Alt Text**
    * Determine which field in the row contains the alt text for the asset. This option will only be shown when the Asset blueprint has an `alt` field.

When importing a Bard field, assets will only be imported when the "Container" config option has been set in the blueprint.


### Relationships
When you're configuring mappings for an Entries, Terms or Users field, there's a few additional config options:

* **Related Key**
    * This field is used to determine how the related item is referenced in the file you're importing. For example: if your import references related items by their Title, you should set this to "Title".
* **Create when missing?**
    * By default, when the importer comes across a related item it can't find in Statamic, it will skip it.
    * However, if you wish, the importer can create the related item for you using the Related Field.


## WordPress
Although the importer is generic and works for importing content from any system, we've built-in some special handling for WordPress imports to make the transition that little bit easier.

### Exporting
While you *can* use WordPress' built-in export tool, it doesn't contain all the fields you might want to import, like ACF fields, at least not in a clean manner.

Instead, we recommend using a plugin like [WP All Export](https://wordpress.org/plugins/wp-all-export/) to export your content. It allows you to customize the columns included in the export (like ACF fields, featured images, etc.) and it'll give you a CSV.

### Classic Editor
If you're importing content from the Classic Editor into a [Bard field](https://statamic.dev/fieldtypes/bard#overview), you will likely want to enable the "WordPress: Replace double line-breaks with <p> tags" option, which will wrap any unwrapped text in `<p>` tags before importing it into Bard.

### Gutenberg
Statamic's [Bard fieldtype](https://statamic.dev/fieldtypes/bard#overview) is the closest equivalent to WordPress' Gutenberg editor.

When you import Gutenberg content into Bard, it will automatically configure Bard sets (which is what we call "blocks") for some of the built-in Gutenberg blocks:

* Paragraphs & Headings
* Tables
* Lists
* Quotes
* Images
* Galleries
* HTML
* Video
* Embed
* Code
* Buttons
* Details
* Separators

If you're using any blocks that aren't on this list, like a custom block or one provided by a plugin, you will need to [hook into the `Gutenberg` class](#hooking-into-gutenberg-blocks) to handle them yourself.


## Extending

### Transformers
Under the hood, transformers are used to transform the data from the import to the format that Statamic expects. 

The importer includes a few transformers out of the box for Core fieldtypes, but you can create your own if you need to transform the data in a different way.

#### Creating a Transformer

1. Create a new class which extends `Statamic\Importer\Transformers\AbstractTransformer`:

    ```php
    // app/ImportTransformers/YourTransformer.php
    
    <?php
    
    namespace App\ImportTransformers;
    
    use Statamic\Importer\Transformers\AbstractTransformer;
    
    class YourTransformer extends AbstractTransformer
    {
        public function transform(string $value)
        {
            // TODO: Implement transform() method.
        }
    }
    ```
   
2. Register the transformer in your `AppServiceProvider`. The first parameter is the handle of the fieldtype you're transforming and the second parameter is the class name of your transformer:

    ```php
    // app/Providers/AppServiceProvider.php
    
    use App\ImportTransformers\YourTransformer;
    use Illuminate\Support\ServiceProvider;
    use Statamic\Importer\Importer;
    use Statamic\Statamic;
    
    class AppServiceProvider extends ServiceProvider
    {
        public function boot(): void
        {
            Statamic::booted(function () {
                Importer::registerTransformer('fieldtype', YourTransformer::class);
            });
        }
    }
    ```
   
3. Now, whenever that fieldtype is encountered during an import, your transformer will be used to transform the data.

#### Config Fields

If you need to, you can add config options for your transformer. These options will be shown in the UI when mapping fields. Simply add a `fieldItems` method to your transformer:

```php
public function fieldItems(): array
{
    return [
        'your_config_field' => [
            'type' => 'text',
            'display' => 'Your Config Field',
        ],
    ];
}
```

You can then access these config options in your transformer via `$this->config('your_config_field')`.

### Hooking into Gutenberg blocks
When the Bard importer detects Gutenberg content, it will automatically handle many of the built-in blocks.

However, if you have custom blocks or wish to override how a certain block is handled, you can [hook](https://statamic.dev/extending/hooks#content) into the `Gutenberg` class to handle them yourself. Simply register a hook in your `AppServiceProvider`:

```php
// app/Providers/AppServiceProvider.phn

use Illuminate\Support\ServiceProvider;
use Statamic\Importer\WordPress\Gutenberg;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gutenberg::hook('core/paragraph', function ($payload, $next) {
            return [
                'type' => 'paragraph',
                'content' => [
                    ['type' => 'text', 'text' => 'Hello, world!'],
                ],
            ];
        });
    }
}
```

You should specify the name of the block you want to handle (including the namespace, like `core/`) and provide a closure returning a Bard array. If you're unsure on what to return, try doing it manually in Bard and look at the array it saves.

The `$payload` that gets passed to the closure is an array containing the block's data, the blueprint, field and the field's mapping config.

## Uninstalling

Once you're done with the importer, you can safely uninstall it by running:

```bash
composer remove statamic/importer
```
