<?php

namespace App\Http\Resources;

use Auth;
use App\User;
use App\Http\Resources\VoucherTermsResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\VoucherTermsResourceCollection;

class VoucherResource extends JsonResource
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
            'url' => $this->url,
            'name' => $this->name,
            'published' => $this->published,
            'value_gbp' => $this->value_gbp,
            'value_eur' => $this->value_eur,
            'subscribe_from_date' => $this->subscribe_from_date,
            'subscribe_to_date' => $this->subscribe_to_date,
            'redeem_from_date' => $this->redeem_from_date,
            'redeem_to_date' => $this->redeem_to_date,
            'redemption_period' => $this->redemption_period,
            'redemption_period_count' => $this->redemption_period_count,
            'public_name' => $this->public_name,
            'page_copy' => $this->page_copy,
            'page_copy_image' => $this->page_copy_image,
            'page_copy_image_url' => $this->page_copy_image_url,
            'unique_code_required' => $this->unique_code_required,
            'limit_pet_required' => $this->limit_pet_required,
            'limit_species_id' => $this->limit_species_id,
            'current_terms' => new VoucherTermsResource($this->currentTerms()->first()),
            $this->mergeWhen( auth('api')->user() and auth('api')->user()->hasPermissionTo( 'see internal data' ), [
                'unique_code_prefix' => $this->unique_code_prefix,
                'unique_codes_url' => $this->unique_codes_url,
                'retrieve_unique_codes_every_count' => $this->retrieve_unique_codes_every_count,
                'retrieve_unique_codes_every_type' => $this->retrieve_unique_codes_every_type,
                'retrieve_unique_codes_every_day_at_time' => $this->retrieve_unique_codes_every_day_at_time,
                'unique_codes_last_retrieve_date' => $this->unique_codes_last_retrieve_date,
                'referrer_points_at_create' => $this->referrer_points_at_create,
                'referrer_points_at_redeem' => $this->referrer_points_at_redeem,
                'limit_per_account' => $this->limit_per_account,
                'limit_per_account_per_date_period' => $this->limit_per_account_per_date_period,
                'limit_per_pet' => $this->limit_per_pet,
                'send_by_email' => $this->send_by_email,
                'email_subject_line' => $this->email_subject_line,
                'email_copy' => $this->email_copy,
                'all_terms' => new VoucherTermsResourceCollection($this->terms),
                'referrer_group_restrictions' => $this->referrerGroupRestrictions,
                'partner_group_restrictions' => $this->partnerGroupRestrictions,
                'limit_to_instant_redemption_partner' => $this->limit_to_instant_redemption_partner,
                'instant_redemption' => $this->instant_redemption,
                'unique_code_label' => $this->unique_code_label,
                'unique_code_placeholder' => $this->unique_code_placeholder,
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
                'created_by' => new UserResource(User::where('id',$this->created_by)->first()),
                'updated_by' => new UserResource(User::where('id',$this->updated_by)->first()),
            ]),
        ];
    }
}
