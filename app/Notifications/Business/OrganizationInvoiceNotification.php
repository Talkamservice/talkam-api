<?php

namespace App\Notifications\Business;

use App\Models\OrganizationInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A company invoice event for the org's admins (web §08 Phase 2a): issued, a
 * due-soon reminder, or an overdue notice. Transactional — always emailed.
 */
class OrganizationInvoiceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public OrganizationInvoice $invoice,
        public string $kind = "issued" // issued | reminder | overdue
    ) {
    }

    public function via(object $notifiable): array
    {
        return ["mail", "database"];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = "₦" . number_format((float) $this->invoice->amount);
        $period = $this->invoice->period_start->format("M j")
            . " – " . $this->invoice->period_end->format("M j, Y");
        $due = optional($this->invoice->due_at)->format("M j, Y");
        [$subject, $intro] = $this->copy();

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Hi {$notifiable->getName()},")
            ->line($intro)
            ->line("Invoice: {$this->invoice->reference}")
            ->line("Billing period: {$period}")
            ->line("Seats billed: {$this->invoice->seats}")
            ->line("Amount due: {$amount}")
            ->line("Due by: {$due}")
            ->line("Settle by bank transfer using the details on your billing screen.");
    }

    public function toArray(object $notifiable): array
    {
        return [
            "type" => "organization_invoice",
            "kind" => $this->kind,
            "invoice_id" => $this->invoice->id,
            "reference" => $this->invoice->reference,
            "amount" => (float) $this->invoice->amount,
            "due_at" => optional($this->invoice->due_at)->toDateString(),
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    private function copy(): array
    {
        return match ($this->kind) {
            "reminder" => [
                "Your TalkAM invoice is due soon",
                "A quick reminder that your TalkAM for Business invoice is due shortly.",
            ],
            "overdue" => [
                "Your TalkAM invoice is overdue",
                "Your TalkAM for Business invoice is past its due date. Please settle it to avoid any interruption to your team's access.",
            ],
            default => [
                "Your TalkAM invoice is ready",
                "Your TalkAM for Business invoice for this billing period has been issued.",
            ],
        };
    }
}
