<?php

namespace App\Services\PlatformAdmin;

use App\Models\OrganizationInvoice;
use App\Models\Payment;

/**
 * Platform-wide revenue aggregate — nothing like this exists anywhere else;
 * Payment/OrganizationInvoice are otherwise only ever queried per-user or
 * per-organization.
 */
class PlatformBillingService
{
    public static function overview(): array
    {
        $consumer_revenue_this_month = (float) Payment::where('status', 'Completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        $org_invoiced_this_month = (float) OrganizationInvoice::where('status', OrganizationInvoice::STATUS_PAID)
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        $mrr = $consumer_revenue_this_month + $org_invoiced_this_month;
        $take_rate_percent = (float) config('therapist.platform_share_percent', 0);

        return [
            "mrr" => $mrr,
            "arr" => $mrr * 12,
            "consumer_revenue_this_month" => $consumer_revenue_this_month,
            "org_invoiced_this_month" => $org_invoiced_this_month,
            "take_rate_percent" => $take_rate_percent,
            "overdue_invoices" => OrganizationInvoice::where('status', OrganizationInvoice::STATUS_DUE)
                ->where('due_at', '<', now())
                ->count(),
        ];
    }

    public static function invoices(int $per_page = 20)
    {
        return OrganizationInvoice::with('organization:id,name')
            ->latest('issued_at')
            ->paginate($per_page);
    }
}
