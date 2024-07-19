<?php

namespace App\Http\Resources\Guildline;

use App\Constants\Post\PostConstants;
use App\Http\Resources\Users\UserResource;
use App\Models\UserCommentReaction;
use Illuminate\Http\Resources\Json\JsonResource;

class GuildlineResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public $resource;

    public function toArray($request)
    {
        return [
            "id" => $this->id,
            "user" => UserResource::custom($this->user),
            "created_at" => formatDate($this->created_at),
            "updated_at" => formatDate($this->updated_at)
        ];
    }
}
