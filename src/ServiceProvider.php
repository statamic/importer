<?php

namespace Statamic\Importer;

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
                ->icon('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="-0.75 -0.75 14 14"><g stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><path d="M2.548 6.324c-.547.271-1.098.571-1.657.91a.927.927 0 0 0-.262 1.345c.934 1.273 1.833 2.291 3.193 3.296a.94.94 0 0 0 .887.117c2.809-1.048 4.547-1.826 6.882-3.221a.94.94 0 0 0 .276-1.37c-.78-1.035-1.474-1.761-2.458-2.55M5.698.446V6.66"/><path d="M7.518 4.84c-.364.728-1.092 1.456-1.82 1.82-.728-.364-1.456-1.092-1.82-1.82"/></g></svg>')
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
