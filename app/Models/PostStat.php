<?php

namespace App\Models;

use App\Constants\Post\PostConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostStat extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, "user_id");
    }

    public function post()
    {
        return $this->belongsTo(Post::class, "post_id");
    }

    public function reactionStats($start_at = null, $end_at = null)
    {
        if (!empty($this?->post_id)) {
            $reactions = UserPostReaction::where('post_id', $this->post_id)
                ->selectRaw('SUM(action = ?) as likes, SUM(action = ?) as dislikes', [PostConstants::LIKE, PostConstants::DISLIKE])
                ->first();

            if (!empty($start_at) && !empty($end_at)) {
                $comments = $this->post->comments()
                    ->whereBetween("created_at", [$start_at, $end_at])
                    ->topLevel()->count();
            } else {
                $comments = $this->post->comments()->topLevel()->count();
            }

            return [
                "likes" => intval($reactions->likes),
                "comments" => intval($comments),
                "dislikes" => intval($reactions->dislikes),
            ];
        }

        return [
            "likes" => 0,
            "comments" => 0,
            "dislikes" => 0,
        ];
    }
}
