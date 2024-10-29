<?php

namespace Statamic\Importer\Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Statamic\Facades\Config;
use Statamic\Facades\Site;
use Statamic\Importer\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    protected function setUp(): void
    {
        parent::setUp();

        $uses = array_flip(class_uses_recursive(static::class));

        if (isset($uses[DatabaseMigrations::class])) {
            Artisan::call('make:queue-batches-table');
        }
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['files']->deleteDirectory(storage_path('app/public'));

        $app['config']->set('statamic.editions.pro', true);

        $app['config']->set('cache.stores.outpost', [
            'driver' => 'file',
            'path' => storage_path('framework/cache/outpost-data'),
        ]);

        $app['config']->set('queue.batching.database', 'testing');
    }

    protected function setSites($sites): void
    {
        Site::setSites($sites);

        Config::set('statamic.system.multisite', Site::hasMultiple());
    }
}
