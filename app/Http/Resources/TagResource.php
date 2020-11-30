<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TagResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'tag' => $this->tag,
            'uuid' => $this->uuid,
            'vouchers' => new VouchersResource($this->whenLoaded('vouchers')),
            'members' => new ConsumersResource($this->whenLoaded('consumers')),
        ];
    }
}
