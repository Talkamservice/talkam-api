<?php

namespace App\Notifications\Therapist;

use App\Helpers\MethodsHelper;
use App\Notifications\Concerns\SendsFirebasePush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "How was your session?" — there was no existing trigger for this anywhere
 * (SessionReviewService::create() only writes the TherapistReview row once
 * the client submits one). Dispatched from SessionLifecycleService::sweep()
 * right after a session becomes SESSION_COMPLETED — rides that existing
 * sweep schedule rather than a new cron job. Client-only: the therapist has
 * nothing to rate here.
 */
class SessionFollowUpNotification extends Notification
{
    use Queueable, SendsFirebasePush;

    public function __construct(public $session)
    {
        //
    }

    public function via(object $notifiable): array
    {
        return MethodsHelper::userNotificationPreference($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("How was your session?")
            ->view('emails.mobile.session-follow-up', [
                "rateSessionUrl" => SessionNotificationSupport::rateUrl($this->session),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }

    public function toDatabase($notifiable)
    {
        return [
            'data' => ['id' => $this->session->id],
            'title' => "How was your session?",
            'message' => "Rate your recent session.",
            'link' => SessionNotificationSupport::rateUrl($this->session),
            'type' => 'therapy_session',
            'batch_no' => null,
        ];
    }
}
