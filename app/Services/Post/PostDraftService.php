<?php

namespace App\Services\Post;

use App\Constants\General\StatusConstants;
use App\Models\Post;
use App\Notifications\Post\SchedulePostNotification;
use Illuminate\Support\Facades\Notification;

class PostDraftService
{

    public function addToSchedule(Post $post)
    {
        $post->update([
            "status" => StatusConstants::SCHEDULED
        ]);

        Notification::send($post->user, new SchedulePostNotification($post));
    }

    public static function list(array $data = [])
    {
        $builder = Post::status(StatusConstants::DRAFTED);

        if (!empty($key = $data["search"] ?? null)) {
            $builder = $builder->search($key);
        }

        return $builder;
    }
}
