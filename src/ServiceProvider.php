<?php

namespace Statamic\Importer;

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

    public function bootAddon()
    {
        foreach ($this->transformers as $fieldtype => $transformer) {
            Importer::registerTransformer($fieldtype, $transformer);
        }
    }
}
