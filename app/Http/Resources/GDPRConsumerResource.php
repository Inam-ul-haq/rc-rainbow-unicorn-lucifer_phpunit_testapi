<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GDPRConsumerResource extends JsonResource
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
            'name_title' => $this->nameTitle->title,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'address_line_1' => $this->address_line_1,
            'town' => $this->town,
            'county' => $this->county,
            'postcode' => $this->postcode,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'blacklisted' => $this->blacklisted ? 'Yes' : 'No',
            'active' => $this->active ? 'Yes' : 'No',
            'deactivated_at' => $this->deactivated_at,
            'blacklisted_at' => $this->blacklisted_at,
            'relationships' => [
                'pets' => new GDPRPetsResource($this->pets),
                'tags' => new GDPRTagsResource($this->tags),
                'coupons' => [
                    'redeemed' => new CouponsResource($this->whenLoaded('redeemedCoupons')),
                    'restricted' => new CouponsResource($this->whenLoaded('restrictedCoupons')),
                ],
                'source' => new ConsumerSourceResource($this->whenLoaded('source')),
            ],
        ];
    }
}
