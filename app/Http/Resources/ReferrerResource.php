<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReferrerResource extends JsonResource
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
            'uuid' => $this->uuid,
            'email' => $this->email,
            'name_title' => $this->nameTitle->title,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'blacklisted' => $this->blacklisted,
            'blacklisted_at' => $this->blacklisted_at,
            'referrer_points' => $this->referrer_points,
        ];
    }
}
