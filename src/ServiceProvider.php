<?php

namespace Statamic\Importer;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Statamic\Exceptions\NotFoundHttpException;
use Statamic\Facades\Utility;
use Statamic\Importer\Facades\Import;
use Statamic\Importer\Http\Controllers\ImportController;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected array $transformers = [
        'assets' => Transformers\AssetsTransformer::class,
        'bard' => Transformers\BardTransformer::class,
        'date' => Transformers\DateTransformer::class,
        'dictionary' => Transformers\DictionaryTransformer::class,
        'entries' => Transformers\EntriesTransformer::class,
        'list' => Transformers\ListTransformer::class,
        'terms' => Transformers\TermsTransformer::class,
        'toggle' => Transformers\ToggleTransformer::class,
        'users' => Transformers\UsersTransformer::class,
    ];

    protected $vite = [
        'publicDirectory' => 'dist',
        'hotFile' => 'vendor/importer/hot',
        'input' => [
            'resources/css/cp.css',
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
                ->description(__('importer::messages.utility_description'))
                ->icon(File::get(__DIR__.'/../resources/svg/icon.svg'))
                ->action([ImportController::class, 'index'])
                ->routes(function ($router) {
                    $router->post('/', [ImportController::class, 'store'])->name('store');
                    $router->get('{import}', [ImportController::class, 'edit'])->name('edit');
                    $router->patch('{import}', [ImportController::class, 'update'])->name('update');
                    $router->delete('{import}', [ImportController::class, 'destroy'])->name('destroy');
                });
        });

        Route::bind('import', function ($id, $route = null) {
            if (! $route) {
                return false;
            }

            $import = Import::find($id);

            throw_if(! $import, new NotFoundHttpException("Import [$id] not found."));

            return $import;
        });
    }
}
