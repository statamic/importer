<?php

namespace Statamic\Importer\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Importer\Imports\Import;
use Statamic\Importer\Support\SortByParent;

class UpdateCollectionTreeJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public Import $import) {}

    public function handle(): void
    {
        $collection = Collection::find($this->import->get('destination.collection'));

        $tree = $collection->structure()?->in($this->import->get('destination.site') ?? Site::default()->handle());

        if (! $tree) {
            return;
        }

        if (! Cache::has("importer.{$this->import->id}.parents")) {
            return;
        }

        $parents = (new SortByParent)->sort(Cache::get("importer.{$this->import->id}.parents"));

        collect($parents)->each(function (array $item) use ($tree) {
            $entry = Entry::find($item['id']);

            $tree->move($entry->id(), $item['parent']);
        });

        $tree->save();

        Cache::forget("importer.{$this->import->id}.parents");
    }
}
