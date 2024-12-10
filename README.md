# Importer
> Allows you to easily import content and users into Statamic. Supports CSV and XML files.

## Features

* Allows importing entries, taxonomy terms, and users
* Supports CSV and XML files
* Easy to use UI for mapping fields
* Special handling of Gutenberg content (WordPress)
* Hooks for customizing the import process

## Installation

You can install the Importer addon via Composer:

``` bash
composer require statamic/importer
```

You can find the Importer addon in the Control Panel under `Utilities > Importer`.

> **Note:**
> Before installing the importer addon, please ensure your project has a database configured. This importer uses a database to keep track of import progress. If you created your site using the [Statamic CLI](https://github.com/statamic/cli), a SQLite database will have been setup for you. You can confirm by running `php artisan migrate`.

<!-- statamic:hide -->
## Documentation

Read the docs on the [Statamic Marketplace](https://statamic.com/addons/statamic/importer/docs) or contribute to it [here on GitHub](DOCUMENTATION.md).
<!-- /statamic:hide -->
