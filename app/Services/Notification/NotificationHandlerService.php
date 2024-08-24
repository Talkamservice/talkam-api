<?php

namespace App\Services\Notification;

use App\Models\ThreadNotification;
use App\Models\User;
use App\Notifications\Comment\NewCommentMentionNotification;
use App\Notifications\Comment\NewCommentNotification;
use App\Notifications\Comment\NewCommentReactionNotification;
use App\Notifications\Post\NewPostReactionNotification;
use App\Notifications\Comment\NewThreadCommentNotification;
use App\Notifications\Comment\NewThreadCommentReactionNotification;
use App\Notifications\Post\NewThreadPostReactionNotification;
use Illuminate\Support\Facades\Notification;

class NotificationHandlerService
{
    protected $user;
    protected $can_receive_talkam_news;
    protected $can_receive_talkam_research;
    protected $comments_notifications_type;
    protected $can_receive_content_activities;
    protected $can_receive_moderation_activities;
    protected $thread_notification_builder;


    public function init($user_id)
    {
        $this->user = User::find($user_id);
        $this->setPreferences();
        $this->thread_notification_builder = ThreadNotification::where("user_id", $user_id)->status();
        return $this;
    }

    public function setPreferences()
    {
        $this->can_receive_talkam_news = $this->canSendNotification("talkam_news");
        $this->can_receive_talkam_research = $this->canSendNotification("talkam_research");
        $this->can_receive_moderation_activities = $this->canSendNotification("moderation_activities");
        $this->can_receive_content_activities = $this->canSendNotification("user_activities");
        $this->comments_notifications_type = $this->user->notificationPreference->comments;
    }

    private function canSendNotification($content, $type = "bool")
    {
        $notification_preference = $this->user->notificationPreference();
        $notification = ($type == "bool") ? $notification_preference->where($content, 1)->first() : $notification_preference->where($content, $type)->first();
        return !empty($notification);
    }

    public function notifyPostOwnerOfNewComment($comment)
    {
        if (
            $this->comments_notifications_type == null ||
            $this->comments_notifications_type == "mentions" ||
            $comment->is_anonymous == 1
        ) {
            return $this;
        }

        Notification::send($comment->post->user, new NewCommentNotification($comment));
        return $this;
    }

    public function notifyThreadUser($model, $type)
    {
        if ($type == "comment") {
            if (!empty($notify_me) && $model->post->user_id != $model->user_id) {
                Notification::send($notify_me->user, new NewThreadCommentNotification($model));
            }
        }

        if ($type == "post_reaction") {
            $notify_me = $this->thread_notification_builder->where("post_id", $model->post_id)->first();
            if (!empty($notify_me) && $model->post->user_id != $model->user_id) {
                Notification::send($notify_me->user, new NewThreadPostReactionNotification($model));
            }
        }

        if ($type == "comment_reaction") {
            $notify_me = $this->thread_notification_builder->where("post_id", $model->comment->post_id)->first();
            if (!empty($notify_me) && $model->comment->user_id != $model->user_id) {
                Notification::send($notify_me->user, new NewThreadCommentReactionNotification($model));
            }
        }

        return $this;
    }

    public function notifyPostOwnerOfNewReaction($post_reaction)
    {
        if ($this->can_receive_content_activities == 1) {
            Notification::send($post_reaction->post->user, new NewPostReactionNotification($post_reaction));
        }

        return $this;
    }

    public function notifyCommentOwnerOfNewComment($comment)
    {
        if ($this->comments_notifications_type == null || $comment->is_anonymous == 1) {
            return $this;
        }

        if ($this->can_receive_content_activities == 1) {
            Notification::send($comment->user, new NewCommentMentionNotification($comment));
        }

        return $this;
    }

    public function notifyCommentOwnerOfNewReaction($comment_reaction)
    {
        if ($this->can_receive_content_activities == 1) {
            Notification::send($comment_reaction->comment->post->user, new NewCommentReactionNotification($comment_reaction));
        }

        return $this;
    }
}
