<?php

namespace App\Services\Post;

use App\Constants\General\StatusConstants;
use App\Models\Post;
use App\Notifications\Post\SchedulePostPublishedNotification;
use Illuminate\Support\Facades\Notification;

class PostEventService
{
    public static function publishScheduledPosts()
    {
        $posts = Post::status(StatusConstants::SCHEDULED)
            ->whereDate("publish_at", "<=", now()->format("Y-m-d H:i:s"))
            ->get();

        foreach ($posts as $key => $post) {
            $post->update([
                "status" => StatusConstants::ACTIVE
            ]);
            
            Notification::send($post->user, new SchedulePostPublishedNotification($post));
        }
    }
}
