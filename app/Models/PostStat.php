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

    public function reactionStats()
    {
        if (!empty($this?->post_id)) {
            $reactions = UserPostReaction::where('post_id', $this->post_id)
                ->selectRaw('SUM(action = ?) as likes, SUM(action = ?) as dislikes', [PostConstants::LIKE, PostConstants::DISLIKE])
                ->first();
    
            $comments = $this->post->comments()->topLevel()->count();
    
            return [
                "likes" => $reactions->likes,
                "comments" => $comments,
                "dislikes" => $reactions->dislikes,
            ];
        }
    
        return [
            "likes" => 0,
            "comments" => 0,
            "dislikes" => 0,
        ];
    }
}
