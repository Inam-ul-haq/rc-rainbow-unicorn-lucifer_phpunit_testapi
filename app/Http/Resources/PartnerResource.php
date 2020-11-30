<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PartnerResource extends JsonResource
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
            'type' => $this->type,
            'subtype' => $this->subtype,
            'public_name' => $this->public_name,
            'public_latitude' => $this->location_point->getLat(),
            'public_longitude' => $this->location_point->getLng(),
            'contact_name_title' => new NameTitleResource( $this->whenLoaded( 'contactNameTitle' ) ),
            'contact_first_name' => $this->contact_first_name,
            'contact_last_name' => $this->contact_last_name,
            'contact_telephone' => $this->contact_telephone,
            'contact_email' => $this->contact_email,
            'public_street_line1' => $this->public_street_line1,
            'public_street_line2' => $this->public_street_line2,
            'public_street_line3' => $this->public_street_line3,
            'public_town' => $this->public_town,
            'public_county' => $this->public_county,
            'public_postcode' => $this->public_postcode,
            'public_country' => $this->public_country,
            'public_email' => $this->public_email,
            'public_vat_number' => $this->public_vat_number,
            'accepts_vouchers' => $this->accepts_vouchers,
            'accepts_loyalty' => $this->accepts_loyalty,
            'crm_id' => $this->crm_id,
            'partner_users' => UserResource::collection( $this->whenLoaded( 'partnerUsers' ) ),
        ];
    }
}
