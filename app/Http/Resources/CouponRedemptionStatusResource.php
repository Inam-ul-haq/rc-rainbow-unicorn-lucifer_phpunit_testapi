<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CouponRedemptionStatusResource extends JsonResource
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
            'status' => $this->status,
            'barcode' => $this->barcode,
            'redeemed_datetime' => $this->redeemed_datetime,
            'redemption_partner_uuid' => $this->redemptionPartner()->first()->uuid,
        ];
    }
}
