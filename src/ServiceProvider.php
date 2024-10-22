<?php

namespace Statamic\Importer;

use Statamic\Facades\Utility;
use Statamic\Importer\Http\Controllers\ImportController;
use Statamic\Importer\Http\Controllers\MappingsController;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    public array $transformers = [
        'assets' => Transformers\AssetsTransformer::class,
        'bard' => Transformers\BardTransformer::class,
        'date' => Transformers\DateTransformer::class,
        'entries' => Transformers\EntriesTransformer::class,
        'terms' => Transformers\TermsTransformer::class,
        'users' => Transformers\UsersTransformer::class,
    ];

    protected $vite = [
        'publicDirectory' => 'resources/dist',
        'hotFile' => 'vendor/importer/hot',
        'input' => [
            'resources/js/cp.js',
        ],
    ];

    public function bootAddon()
    {
        foreach ($this->transformers as $fieldtype => $transformer) {
            Importer::registerTransformer($fieldtype, $transformer);
        }

        Utility::extend(function () {
            Utility::register('import')
                ->action([ImportController::class, 'index'])
                ->routes(function ($router) {
                    $router->post('/', [ImportController::class, 'store'])->name('store');
                    $router->post('mappings', MappingsController::class)->name('mappings');
                });
        });
    }
}
