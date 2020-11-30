<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReferrerPointsResource extends JsonResource
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
            'id' => $this->transaction_id,
            'date' => $this->transaction_date,
            'transaction_type' => $this->transaction_type,
            'transaction_points' => $this->points,
            'voucher_uuid' => $this->voucher_uuid,
            'consumer' => [
                'uuid' => $this->consumer_uuid,
                'name' => $this->consumer_firstname . ' ' .
                            $this->consumer_lastname,
                'email' => $this->consumer_email,
            ],
            'referrer' => [
                'uuid' => $this->referrer_uuid,
                'name' => $this->referrer_firstname . ' ' .
                          $this->referrer_lastname,
                'email' => $this->referrer_email,
            ],
        ];
    }
}
