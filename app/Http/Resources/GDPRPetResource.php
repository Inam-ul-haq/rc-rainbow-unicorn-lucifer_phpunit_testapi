<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GDPRPetResource extends JsonResource
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
            'pet_name' => $this->pet_name,
            'pet_dob' => $this->pet_dob,
            'pet_gender' => $this->pet_gender,
            'breed' => $this->breed->breed_name,
            'species' => $this->breed->species->species_name,
            'weights' => new GDPRPetWeightsResource($this->weights),
        ];
    }
}
