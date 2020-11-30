<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VoucherTermsResource extends JsonResource
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
            'id' => $this->id,
            'voucher_uuid' => \App\Voucher::where('id',$this->voucher_id)->first()->uuid,
            'voucher_terms' => $this->voucher_terms,
            'used_from' => $this->used_from,
            'used_until' => $this->used_until,
        ];
    }
}
