<?php

namespace Statamic\Importer\Tests;

use Statamic\Importer\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    public function setUp(): void
    {
        parent::setUp();

        $this->app['files']->deleteDirectory(storage_path('app/public'));
    }
}
