<?php

namespace App\Http\Resources\Users;

use App\Helpers\MethodsHelper;
use App\Http\Resources\Finance\Subscription\SubscriptionResource;
use App\Http\Resources\Location\CountryResource;
use App\Http\Resources\Location\StateResource;
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

        $i_am_blocked = BlockedUser::where([
            "blocker_id" => $this->id,
            "blocked_user_id" => auth("sanctum")->id(),
        ])->exists();

        $currency_code_ = MethodsHelper::validateCurrencyCode(app("position_country_code")) ?? "USD";

        return [
            "id" => (int) $this->id,
            "avatar" => $this->avatar,
            "name" => $this->full_name,
            "email" => (string) $this->email,
            "role" => $this->role,
            "age" => $this->age,
            "username" => (string) $this->username,
            "google_id" => $this->google_id,
            "facebook_id" => $this->facebook_id,
            "tiktok_id" => $this->social_id,
            "apple_id" => $this->apple_user_id,
            "is_blocked" => $is_blocked,
            "i_am_blocked" => $i_am_blocked,
            "status" => (string) $this->status,
            "gender" => ucfirst($this->gender),
            "anonymous_post" => ($this->anonymous_post + $this->anonymous_comment) ?? 0,
            "anonymous_comment" => ($this->anonymous_comment + $this->anonymous_post) ?? 0,
            "public_group_count" => $this->public_group_count ?? 0,
            "date_of_birth" => formatDateOfBirth($this->date_of_birth),
            "should_display_ads" => $this->should_display_ads,
            "pricing_currency" => $currency_code_,
            "active_subscription" => !empty($this->activeSubscription) ? SubscriptionResource::custom($this->activeSubscription) : null,
            "state" => !empty($this->state) ? StateResource::make($this->whenLoaded("state", $this->state)) : null,
            "country" => !empty($this->country) ? CountryResource::make($this->whenLoaded("country", $this->country)) : null,
            "interests" => InterestResource::collection($this->whenLoaded("interests", $this->interests)),
            "email_verified_at" => formatDate($this->email_verified_at),
            "created_at" => formatDate($this->created_at),
            "updated_at" => formatDate($this->updated_at)
        ];
    }

    /**
     * $following_ids: pass the authenticated user's followed-user ids to
     * include an "is_following" flag (omitted when null, so every other
     * caller of custom()/customCollection() keeps its exact current shape).
     * A set, not a per-call query — see customCollection().
     */
    public static function custom($model, ?array $following_ids = null)
    {
        $data = [
            "id" => (int) $model->id,
            "avatar" => $model->avatar,
            "name" => $model->full_name,
            "username" => $model->username,
            "active_subscription" => !empty($model->activeSubscription) ? SubscriptionResource::custom($model->activeSubscription) : null,
            "email" => (string) $model->email,
        ];

        if ($following_ids !== null) {
            $data["is_following"] = in_array($model->id, $following_ids);
        }

        return $data;
    }

    /**
     * $following_ids: forwarded to custom() for every item — compute this
     * once per request (e.g. UserFollow::where("follower_id", auth()->id())
     * ->pluck("followed_id")->all()), never per-item, to avoid an N+1 query
     * across the collection.
     */
    public static function customCollection($collections, ?array $following_ids = null)
    {
        return $collections->map(function ($model) use ($following_ids) {
            return self::custom($model, $following_ids);
        });
    }
}
