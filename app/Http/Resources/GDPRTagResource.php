<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GDPRTagResource extends JsonResource
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
            'signup_datetime' => $this->pivot->created_at,
        ];
    }
}
