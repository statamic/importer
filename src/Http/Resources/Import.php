<?php

namespace Statamic\Importer\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class Import extends JsonResource
{
    public function toArray($request)
    {
        $data = [
            'id' => $this->resource->id(),
            'name' => $this->resource->name(),
        ];

        return ['data' => $data];
    }
}
