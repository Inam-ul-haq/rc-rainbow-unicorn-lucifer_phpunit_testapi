<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PersonalAccessTokenResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        if ($this->token) {
            return [
                'id' => $this->token->id,
                'user_id' => $this->token->user_id,
                'name' => $this->token->name,
                'created_at' => $this->token->created_at,
                'revoked' => $this->token->revoked,
                'expires_at' => $this->token->expires_at,
                'accessToken' => $this->when($this->accessToken, $this->accessToken),
            ];

        } else {
            return [
                'id' => $this->id,
                'user_id' => $this->user_id,
                'name' => $this->name,
                'created_at' => $this->created_at,
                'revoked' => $this->revoked,
                'expires_at' => $this->expires_at,
            ];

        }
    }
}
