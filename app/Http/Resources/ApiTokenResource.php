<?php

namespace App\Http\Resources;

use App\Http\Resources\UserRolesResource;
use App\Http\Resources\UserPermissionsResource;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiTokenResource extends JsonResource
{
    private $token;
    private $expires;
    private $token_type;

    public function __construct($resource, $token, $token_type, $expires)
    {
        parent::__construct($resource);
        $this->resource = $resource;

        $this->token = $token;
        $this->expires = $expires;
        $this->token_type = $token_type;
    }

    public function toArray($request)
    {
        return [
            'expires_in' => $this->expires,
            'token_type' => $this->token_type,
            'access_token' => $this->token,
        ];
    }
}
