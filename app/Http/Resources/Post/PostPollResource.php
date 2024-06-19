<?php

namespace App\Http\Resources\Post;

use App\Models\UserPollChoice;
use Illuminate\Http\Resources\Json\JsonResource;

class PostPollResource extends JsonResource
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
        $choice = UserPollChoice::where([
            "poll_id" => $this->id,
            "user_id" => auth()->id(),
        ])->exists();

        return [
            "id" => $this->id,
            "option" => $this->option,
            "type" => $this->type,
            "selected" => $choice,
            "created_at" => formatDate($this->created_at),
        ];
    }
}
