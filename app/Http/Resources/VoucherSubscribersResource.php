<?php

namespace App\Http\Resources;

use App\Http\Resources\VoucherSubscriberResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

class VoucherSubscribersResource extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return VoucherSubscriberResource::collection($this->collection);
    }
}
