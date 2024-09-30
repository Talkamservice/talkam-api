<?php

namespace App\Models;

use App\Constants\General\StatusConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        "views_count" => "integer",
        "can_comment" => "integer",
        "is_anonymous" => "integer",
        "tags" => "array"
    ];

    public function user()
    {
        return $this->belongsTo(User::class, "user_id");
    }

    public function scopeStatus($query, $status = StatusConstants::ACTIVE)
    {
        return $query->where("status", $status);
    }

    public function category()
    {
        return $this->belongsTo(PostCategory::class, "category_id");
    }

    public function attachments()
    {
        return $this->hasMany(PostAttachment::class, "post_id");
    }

    public function comments()
    {
        return $this->hasMany(PostComment::class, "post_id");
    }

    public function reactions()
    {
        return $this->hasMany(UserPostReaction::class, "post_id");
    }

    public function group()
    {
        return $this->belongsTo(Group::class, "group_id");
    }

    public function polls()
    {
        return $this->hasMany(PostPoll::class, "post_id");
    }

    public function pollChoices()
    {
        return $this->hasMany(UserPollChoice::class, "post_id");
    }

    public function scopeSearch($query, $key)
    {
        $query->where(function ($query) use ($key) {
            $query->where("title", "LIKE", "%$key%")
                ->orWhere("body", "LIKE", "%$key%")
                ->orWhere("type", "LIKE", "%$key%")
                ->orWhere("uuid", "LIKE", "%$key%")
                ->orWhere("tags", "LIKE", "%$key%")
                ->orWhereHas("user", function ($user) use ($key) {
                    $user->search($key);
                })->orWhereHas("category", function ($category) use ($key) {
                    $category->search($key);
                });
        });
    }

    public function scopeUnblocked($query)
    {
        if (auth("sanctum")->check()) {
            //All users that blocked me
            $blocked_me_users = BlockedUser::where("blocked_user_id", auth("sanctum")->id())->pluck("blocker_id")->toArray();
            //All users that I blocked
            $blocked_users = BlockedUser::where("blocker_id", auth("sanctum")->id())->pluck("blocked_user_id")->toArray();

            $query->whereNotIn('user_id', array_merge($blocked_me_users, $blocked_users));
        }

        return $query;
    }

    public function scopeAnonymous($query, $anonymous = 0)
    {
        return $query->where("is_anonymous", $anonymous);
    }

    public function threadNotifications()
    {
        return $this->hasMany(ThreadNotification::class, "post_id");
    }

    public function postType($type)
    {
        switch ($type) {
            case 'Poll':
                return $this->polls;
            case 'Text':
                return ['title' => $this->title, 'body' => $this->body];
            case 'File':
                return [
                    'title' => $this->title,
                    'attachments' => $this->attachments
                ];
            default:
                return ['title' => $this->title, 'body' => $this->body];
        }
    }

    public function postReports()
    {
        return $this->hasMany(PostReport::class, 'post_id');
    }
}
