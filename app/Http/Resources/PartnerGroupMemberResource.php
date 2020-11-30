<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PartnerGroupMemberResource extends JsonResource
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
            'crm_id' => $this->crm_id,
            'public_name' => $this->public_name,
            'public_town' => $this->public_town,
            'type' => $this->type,
        ];
    }
}
