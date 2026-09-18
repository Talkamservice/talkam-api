<?php

namespace App\Notifications\Business;

use App\Models\Organization;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The receipt for a TalkAM for Business session-bundle purchase
 * (OrganizationBillingService::fulfilBundlePayment). Previously this event
 * piggybacked on the generic ad/promotion NewPaymentNotification, which has
 * no message branch for PAYMENT_FOR_BUSINESS_BUNDLE — business admins were
 * getting a "New Payment!" email with an empty body. This is its own
 * notification so that bug can't resurface by another activity type reusing
 * the same untouched branch.
 */
class BusinessBundlePaymentReceiptNotification extends Notification
{
    use Queueable;

    public function __construct(public Payment $payment, public Organization $organization)
    {
    }

    public function via(object $notifiable): array
    {
        return ["mail"];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $meta = $this->payment->metadata ?? [];
        $bundle_sessions = (int) ($meta["bundle_sessions"] ?? 0);
        $bundle_charge = (float) ($meta["bundle_charge"] ?? 0);
        $session_unit_price = $bundle_sessions > 0 ? $bundle_charge / $bundle_sessions : 0;

        $payment_method = $this->organization->card_brand
            ? trim("{$this->organization->card_brand} •••• {$this->organization->card_last4}")
            : "Card";

        return (new MailMessage)
            ->subject("Payment received — receipt from Flutterwave")
            ->view('emails.business.payment-receipt', [
                "bundleSessions" => $bundle_sessions,
                "sessionUnitPrice" => format_money($session_unit_price, 2, "₦"),
                "bundleSubtotal" => format_money($bundle_charge, 2, "₦"),
                "paymentMethod" => $payment_method,
                // No auto-charge exists anywhere in this codebase — business
                // billing is invoice-and-pay (OrganizationBillingRunService),
                // never a recurring card debit. This is the next invoice's
                // due date, not a charge date; the template copy says so.
                "nextChargeDate" => now()->addMonthNoOverflow()->startOfMonth()->format('M j, Y'),
                "receiptUrl" => rtrim((string) config("business.web_url"), "/") . "/business/admin/billing",
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
