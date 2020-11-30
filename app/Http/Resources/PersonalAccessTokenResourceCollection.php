<?php

namespace App\Http\Resources;

use App\Http\Resources\PersonalAccessTokenResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PersonalAccessTokenResourceCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return PersonalAccessTokenResource::collection($this->collection);
    }
}
