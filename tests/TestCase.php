<?php

namespace Statamic\Importer\Tests;

use Orchestra\Testbench\Attributes\WithMigration;
use Statamic\Facades\Config;
use Statamic\Facades\Site;
use Statamic\Importer\ServiceProvider;
use Statamic\Testing\AddonTestCase;

#[WithMigration('queue')]
abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

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
