<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VoucherSubscriberResource extends JsonResource
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
            'coupon_uuid' => $this->coupon_uuid,
            'consumer_uuid' => $this->consumer_uuid,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'voucher_name' => $this->voucher_name,
            'status' => $this->status,
            'unique_code_used' => $this->unique_code,
            'access_code_used' => $this->access_code,
            'issued_at' => $this->issued_at,
            'redeemed_at' => $this->redeemed_datetime,
        ];
    }
}
