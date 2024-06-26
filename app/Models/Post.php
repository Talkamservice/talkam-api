<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        "views_count" => "integer",
        "can_comment" => "integer",
        "is_anonymous" => "integer"
    ];

    public function user()
    {
        return $this->belongsTo(User::class, "user_id");
    }

    public function category()
    {
        return $this->belongsTo(PostCategory::class, "category_id");
    }

    public function attachments()
    {
        return $this->hasMany(PostAttachment::class, "post_id");
    }

    public function polls()
    {
        return $this->hasMany(PostPoll::class, "post_id");
    }

    public function scopeSchedule($query, $action = "current")
    {
        if ($action == "current") {
            $query->where(function ($post) {
                $post->whereNull("publish_at")
                    ->orWhere("publish_at", "=<", now()->format("Y-m-d H:i:s"));
            });
        } else {
            $query->where(function ($post) {
                $post->whereNotNull("publish_at")
                    ->orWhere("publish_at", ">", now()->format("Y-m-d H:i:s"));
            });
        }

        return $query;
    }

    public function scopeUnblocked($query, $user_id = null)
    {
        $user_id = $user_id ?? auth()->user()->id;
        $query->user()->where(function ($q) use ($user_id) {
            $q->whereHasNot("blockedUsers")
                ->orWhereHas("blockedUsers", function ($user) use ($user_id) {
                    $user->whereNot("blocked_user_id", $user_id);
                });
        });

        return $query;
    }
}
