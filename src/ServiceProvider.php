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
        'publicDirectory' => 'dist',
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
            Utility::register('importer')
                ->title(__('Importer'))
                ->description(__('Import entries, taxonomies, and users from XML and CSV files.'))
                ->icon('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="m4.5 8.5 9.5 0" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1"></path><path d="m4.5 11.5 6 0" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1"></path><path d="m4.5 5.5 7 0" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1"></path><path d="m4.5 14.5 4 0" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1"></path><path d="m4.5 17.5 4 0" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1"></path><path d="M10.5 23.5h-9a1 1 0 0 1 -1 -1v-21a1 1 0 0 1 1 -1h13.293a1 1 0 0 1 0.707 0.293L19.207 4.5a1 1 0 0 1 0.293 0.707V8.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1"></path><path d="M11.5 17.5a6 6 0 1 0 12 0 6 6 0 1 0 -12 0" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1"></path><path d="m17.5 14.5 0 6" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1"></path><path d="m17.5 20.5 -2.25 -2.25" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1"></path><path d="m17.5 20.5 2.25 -2.25" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1"></path></svg>')
                ->action([ImportController::class, 'index'])
                ->routes(function ($router) {
                    $router->post('/', [ImportController::class, 'store'])->name('store');
                    $router->get('{import}', [ImportController::class, 'edit'])->name('edit');
                    $router->patch('{import}', [ImportController::class, 'update'])->name('update');
                    $router->delete('{import}', [ImportController::class, 'destroy'])->name('destroy');

                    $router->post('mappings', MappingsController::class)->name('mappings');
                });
        });
    }
}
