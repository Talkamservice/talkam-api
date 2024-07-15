<?php

namespace App\Http\Resources\Post;

use App\Services\Post\PostReactionService;
use Illuminate\Http\Resources\Json\JsonResource;

class PostReactionResource extends JsonResource
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
        $status = PostReactionService::isReactionPresent($this->post_id, $this->user_id, $this->action);
        return [
            "id" => $this->id,
            "action" => $this->action,
            "status" => $this->status,
            "created_at" => formatDate($this->created_at),
        ];
    }
}
