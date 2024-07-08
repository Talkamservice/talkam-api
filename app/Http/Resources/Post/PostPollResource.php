<?php

namespace App\Http\Resources\Post;

use App\Models\UserPollChoice;
use App\Services\Post\PostPollService;
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
        ])->first();

        $count = UserPollChoice::where([
            "poll_id" => $this->id,
        ])->count();

        return [
            "id" => $this->id,
            "option" => $this->option,
            "type" => $this->type,
            "duration" => $this->duration,
            "selected" => !empty($choice),
            "count" => $count,
            "percentage" => (new PostPollService)->pollPercentage($this->id),
            "anonymous" => $choice?->anonymous,
            "created_at" => formatDate($this->created_at),
        ];
    }
}
