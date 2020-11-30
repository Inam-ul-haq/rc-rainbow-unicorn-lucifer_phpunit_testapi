<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class UserApiKeyResourceCollection extends ResourceCollection
{
    protected $withoutFields = [];
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return $this->processCollection($request);
    }

    public function hide(array $fields)
    {
        $this->withoutFields = $fields;
        return $this;
    }

    protected function processCollection($request)
    {
        return $this->collection->map(
            function (UserApiKeyResource $resource) use ($request) {
                return $resource->hide($this->withoutFields)->toArray($request);
            })->all();
    }
}
