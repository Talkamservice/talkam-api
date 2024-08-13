<?php

namespace App\Http\Resources\Post;

use App\Models\UserPollChoice;
use App\Services\Post\PostPollService;
use Carbon\Carbon;
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
            "user_id" => auth("sanctum")->id(),
        ])->first();

        $count = UserPollChoice::where([
            "poll_id" => $this->id,
        ])->count();

        $expires_at = Carbon::parse($this->post?->publish_at ?? $this->created_at)->addMinutes($this->duration)->format("Y-m-d H:i:s");

        return [
            "id" => $this->id,
            "option" => $this->option,
            "type" => $this->type,
            "duration" => $this->duration,
            "selected" => !empty($choice),
            "count" => $count,
            "percentage" => (new PostPollService)->pollPercentage($this->id),
            "anonymous" => $choice?->anonymous,
            "expires_at" => formatDate($expires_at),
            "created_at" => formatDate($this->created_at),
        ];
    }
}
