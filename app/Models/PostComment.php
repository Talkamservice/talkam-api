<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostComment extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        "is_anonymous" => "integer",
    ];

    public function user()
    {
        return $this->belongsTo(User::class, "user_id");
    }

    public function post()
    {
        return $this->belongsTo(Post::class, "post_id");
    }

    public function threadNotifications()
    {
        return $this->hasMany(ThreadNotification::class, "comment_id");
    }


    public function parent()
    {
        return $this->belongsTo(self::class, "parent_id");
    }

    public function children()
    {
        return $this->hasMany(self::class, "parent_id");
    }

    public function repliedComment()
    {
        return $this->belongsTo(self::class, "reply_comment_id");
    }

    // This method here is used to count top-level comment only
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }


    public function reportedComment()
    {
        return $this->hasMany(self::class, "comment_report_id");
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
}
