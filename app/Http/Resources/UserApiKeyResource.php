<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserApiKeyResource extends JsonResource
{
    protected $withoutFields = [];
    private $secret = null;

    public function __construct($resource, $secret='')
    {
        parent::__construct($resource);
        $this->secret = $secret;
    }

    public static function collection($resource)
    {
        return tap(new UserApiKeyResourceCollection($resource), function ($collection) {
            $collection->collects = __CLASS__;
        });
    }

    public function toArray($request)
    {
        return $this->filterFields([
            'id' => $this->id,
            'user_id' => $this->user_id,
            'source_id' => $this->source_id,
            'expires_at' => $this->expires_at,
            'api_key' => $this->api_key,
            'secret' => $this->secret,
            'source' => $this->whenLoaded('consumerSource', function() {
                return $this->consumerSource->source_name;
            }),
        ]);
    }

    public function hide(array $fields)
    {
        $this->withoutFields = $fields;
        return $this;
    }

    protected function filterFields($array)
    {
        return collect($array)->forget($this->withoutFields)->toArray();
    }
}
