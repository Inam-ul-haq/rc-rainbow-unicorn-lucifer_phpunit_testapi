<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Auth;
use App\Http\Resources\VoucherResource;
use App\Http\Resources\PartnerResource;

class CouponResource extends JsonResource
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
            'issued_at' => $this->issued_at,
            'voucher_uuid' => $this->voucher_uuid,
            'barcode' => $this->barcode,
            'valid_from' => $this->valid_from,
            'valid_to' => $this->valid_to,
            'redeemed_datetime' => $this->redeemed_datetime,
            'redemption_method' => $this->redemption_method,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            $this->mergeWhen( Auth::user()->hasPermissionTo( 'see internal data' ), [
                'cancelled_at' => $this->cancelled_at,
                'cancelled' => $this->cancelled,
                'vouchers_unique_codes_used_id' => $this->vouchers_unique_codes_used_id,
                'redeemed_by_consumer_uuid' => $this->redeemed_by_consumer_id ? \App\Consumer::where('id',$this->redeemed_by_consumer_id)->first()->uuid : null,
                'restrict_consumer_uuid' => $this->restrict_consumer_id ? \App\Consumer::where('id',$this->restrict_consumer_id)->first()->uuid : null,
                'restrict_partner_uuid' => $this->restrict_partner_id ? \App\Partner::where('id',$this->restrict_partner_id)->first()->uuid : null,
                'referrer' => new ReferrerResource($this->whenLoaded('referrer')),
                'maximum_uses' => $this->maximum_uses,
                'shared_code' => $this->shared_code,
                'redemption_partner_uuid' => $this->redemption_partner_id ? \App\Partner::where('id',$this->redemption_partner_id)->first()->uuid : null,
                'reissued_as_coupon_uuid' => $this->reissued_as_coupon_id ? \App\Coupon::where('id',$this->reissued_as_coupon_id)->first()->uuid : null,
                'redemption_partner' => new PartnerResource($this->whenLoaded('redemptionPartner')),
                'restricted_to_partner' => new PartnerResource($this->whenLoaded('restrictPartner')),
            ]),
            'status' => $this->status,
            'voucher' => new VoucherResource( $this->voucher ),
        ];
    }
}
