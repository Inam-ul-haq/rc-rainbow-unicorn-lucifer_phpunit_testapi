<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PetResource extends JsonResource
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
            'consumer_uuid' => $this->consumer->uuid,
            'pet_name' => $this->pet_name,
            'pet_dob' => $this->pet_dob,
            'pet_gender' => $this->pet_gender,
            'neutered' => $this->neutered,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'breed' => new BreedResource($this->breed),
            'weights' => $this->weights,
        ];
    }
}
