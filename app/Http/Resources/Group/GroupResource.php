<?php

namespace App\Http\Resources\Group;

use App\Constants\Account\User\UserConstants;
use App\Http\Resources\General\FileResource;
use App\Http\Resources\Location\CountryResource;
use App\Http\Resources\Location\LgaResource;
use App\Http\Resources\Location\StateResource;
use App\Http\Resources\Location\TownResource;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */

    public function toArray($request)
    {
        return [
            "id" => $this->id,
            "name" => $this->name,
            "uuid" => $this->uuid,
            "category" => $this->category,
            "status" => $this->status,
            "image" => $this->image,
            "description" => $this->description,
            "rules" => $this->rules,
            "followers" => $this->followers
        ];
    }
}
