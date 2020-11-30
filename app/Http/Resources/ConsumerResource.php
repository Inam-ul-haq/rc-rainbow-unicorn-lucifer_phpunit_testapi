<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ConsumerResource extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'uuid' => $this->uuid,
            'name_title' => $this->nameTitle->title,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'crm_id' => $this->crm_id,
            'last_update_from_crm' => $this->last_update_from_crm,
            'address_line_1' => $this->address_line_1,
            'town' => $this->town,
            'county' => $this->county,
            'country' => $this->country,
            'postcode' => $this->postcode,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'blacklisted' => $this->blacklisted,
            'active' => $this->active,
            'deactivated_at' => $this->deactivated_at,
            'blacklisted_at' => $this->blacklisted_at,
            'password_change_needed' => $this->password_change_needed,
            'source' => $this->source,
            'relationships' => [
                'name_title' => new NameTitleResource($this->whenLoaded('nameTitle')),
                'pets' => new PetsResource($this->whenLoaded('pets')),
                'tags' => new TagsResource($this->whenLoaded('tags')),
                'coupons' => [
                    'redeemed' => new CouponsResource($this->whenLoaded('redeemedCoupons')),
                    'restricted' => new CouponsResource($this->whenLoaded('restrictedCoupons')),
                ],
            ],
        ];
    }
}
