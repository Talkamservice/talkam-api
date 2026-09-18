<?php

namespace App\Notifications\Business;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A Wellbeing Plus (2,000+ seats, custom pricing) lead from the Billing
 * screen's "Compare plans" view — sent to TalkAM's own sudo() admin, not the
 * org's, since it's the org ASKING TalkAM to build them a plan.
 */
class CustomPlanQuoteRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Organization $organization,
        public string $requester_name,
        public string $contact_email,
        public ?int $team_size,
        public ?string $notes
    ) {
    }

    public function via(object $notifiable): array
    {
        return ["mail", "database"];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Custom plan request: {$this->organization->name}")
            ->view("emails.business.custom-plan-quote-request", [
                "organizationName" => $this->organization->name,
                "requesterName" => $this->requester_name,
                "contactEmail" => $this->contact_email,
                "teamSize" => $this->team_size ? number_format($this->team_size) : null,
                "notes" => $this->notes,
                "adminUrl" => route("admin.custom-plan-quote-requests.index"),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            "type" => "custom_plan_quote_request",
            "organization_id" => $this->organization->id,
            "organization_name" => $this->organization->name,
            "requester_name" => $this->requester_name,
            "contact_email" => $this->contact_email,
            "team_size" => $this->team_size,
            "notes" => $this->notes,
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
