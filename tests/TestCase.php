<?php

namespace Statamic\Importer\Tests;

use Statamic\Facades\Config;
use Statamic\Facades\Site;
use Statamic\Importer\ServiceProvider;
use Statamic\Testing\AddonTestCase;

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
    }

    protected function setSites($sites)
    {
        Site::setSites($sites);

        Config::set('statamic.system.multisite', Site::hasMultiple());
    }
}
