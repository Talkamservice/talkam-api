<?php

namespace App\Http\Resources\Users;

use App\Models\BlockedUser;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $is_blocked = BlockedUser::where([
            "blocker_id" => auth("sanctum")->id(),
            "blocked_user_id" => $this->id,
        ])->exists();
        
        return [
            "id" => (int) $this->id,
            "avatar" => $this->avatar,
            "name" => $this->full_name,
            "email" => (string) $this->email,
            "role" => $this->role,
            "age" => $this->age,
            "username" => (string) $this->username,
            "status" => (string) $this->status,
            "google_id" => $this->google_id,
            "facebook_id" => $this->facebook_id,
            "tiktok_id" => $this->social_id,
            "apple_id" => $this->apple_user_id,
            "is_blocked" => $is_blocked,
            "interests" => InterestResource::collection($this->whenLoaded("interests", $this->interests)),
            "email_verified_at" => formatDate($this->email_verified_at),
            "created_at" => formatDate($this->created_at),
            "updated_at" => formatDate($this->updated_at)
        ];
    }

    public static function custom($model)
    {
        return [
            "id" => (int) $model->id,
            "avatar" => $model->avatar,
            "name" => $model->full_name,
            "username" => $model->username,
            "email" => (string) $model->email,
        ];
    }
}
