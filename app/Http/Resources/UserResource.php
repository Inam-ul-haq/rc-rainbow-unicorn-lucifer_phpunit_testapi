<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $return = [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'blocked' => $this->blocked,
            'blocked_at' => $this->blocked_at,
            'created_at' => (string) $this->created_at,
            'name_title' => new NameTitleResource( $this->nameTitle ),
            'updated_at' => (string) $this->updated_at,
            'name_title_id' => $this->name_title_id,
            'email_verified_at' => $this->email_verified_at,
            'password_change_needed' => $this->password_change_needed,
        ];
        if (isset($this->pivot)) {
            $return['manager'] = $this->pivot->manager;
        }
        return $return;
    }
}
