<?php

namespace App\Notifications\Feedback;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResponseToFeedbackNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $responseMessage;
    protected $user;

    /**
     * Create a new notification instance.
     */
    public function __construct($user, $responseMessage)
    {
        $this->user = $user;
        $this->responseMessage = $responseMessage;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        // Build data for the email template
        $data = $this->buildData($notifiable);

        // Use markdown for sending the feedback response email
        return (new MailMessage)
            ->subject($data['title'])
            ->markdown('emails.feedback.response', [
                'title' => $data['title'],
                'message' => $data['message'],
                'recipient_name' => $this->user->name, 
            ]);
    }

    /**
     * Build the data for the email notification.
     */
    protected function buildData($notifiable): array
    {
        return [
            'title' => "Response to Your Feedback",
            'message' => $this->responseMessage,
        ];
    }

    /**
     * Get the array representation of the notification (optional).
     */
    public function toArray($notifiable): array
    {
        return [
            'feedback_response' => $this->responseMessage,
        ];
    }
}
