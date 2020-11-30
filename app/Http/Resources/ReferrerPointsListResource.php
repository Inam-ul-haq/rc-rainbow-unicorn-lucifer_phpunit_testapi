<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ReferrerPointsListResource extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'data' => ReferrerPointsResource::collection($this->collection),
        ];
    }
}
