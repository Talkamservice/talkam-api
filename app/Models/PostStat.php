<?php

namespace App\Models;

use App\Constants\Post\PostConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
                ->selectRaw('SUM(action = ?) as likes, SUM(action = ?) as dislikes', [PostConstants::LIKE, PostConstants::DISLIKE]);

            if (!empty($start_at) && !empty($end_at)) {
                $reactions =  $reactions->whereBetween("created_at", [$start_at, $end_at]);
            }

            $reactions =  $reactions->first();

            if (!empty($start_at) && !empty($end_at)) {
                $comments = $this->post->comments()
                    ->whereBetween("created_at", [$start_at, $end_at])
                    ->topLevel()->count();
            } else {
                $comments = $this->post->comments()->topLevel()->count();
            }

            $distinct_users = DB::table(function ($query) use ($start_at, $end_at) {
                $query->select('user_id')
                    ->from('user_post_reactions')
                    ->where('post_id', $this->post_id)
                    ->when(!empty($start_at) && !empty($end_at), function ($query) use ($start_at, $end_at) {
                        $query->whereBetween('created_at', [$start_at, $end_at]);
                    })
                    ->union(
                        DB::table('post_comments')
                            ->select('user_id')
                            ->where('post_id', $this->post_id)
                            ->whereNull('parent_id')
                            ->when(!empty($start_at) && !empty($end_at), function ($query) use ($start_at, $end_at) {
                                $query->whereBetween('created_at', [$start_at, $end_at]);
                            })
                    );
            })->distinct()->pluck("user_id");

            return [
                "likes" => intval($reactions->likes),
                "comments" => intval($comments),
                "dislikes" => intval($reactions->dislikes),
                "users" => intval($distinct_users),
            ];
        }

        return [
            "likes" => 0,
            "comments" => 0,
            "dislikes" => 0,
            "users" => 0
        ];
    }
}
