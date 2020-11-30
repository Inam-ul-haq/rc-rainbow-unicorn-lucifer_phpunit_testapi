<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GDPRPetWeightResource extends JsonResource
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
            'pet_weight' => $this->pet_weight,
            'date_entered' => $this->date_entered,
        ];
    }
}
