<?php

namespace App\Services\PlatformAdmin;

use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\CustomPlanQuoteRequest;

/**
 * Wellbeing Plus (custom pricing) leads — submitted from the Billing
 * screen's "Compare plans" view (App\Services\Business\OrganizationService::
 * requestCustomQuote) and, until now, only ever visible via the legacy
 * Blade admin panel (App\Http\Controllers\Admin\Business\
 * CustomPlanQuoteRequestController) or the sudo admin's own inbox
 * (App\Notifications\Business\CustomPlanQuoteRequestNotification). This is
 * the platform-admin API port of that same read + "mark contacted" action —
 * status values are the same raw lowercase strings the model already uses
 * ('pending'/'contacted'; no StatusConstants entry fits this).
 */
class PlatformCustomQuoteRequestService
{
    const STATUS_PENDING = 'pending';
    const STATUS_CONTACTED = 'contacted';

    public static function overview(): array
    {
        return [
            "pending" => CustomPlanQuoteRequest::where('status', self::STATUS_PENDING)->count(),
            "contacted_this_month" => CustomPlanQuoteRequest::where('status', self::STATUS_CONTACTED)
                ->whereMonth('contacted_at', now()->month)
                ->whereYear('contacted_at', now()->year)
                ->count(),
            "total_requests" => CustomPlanQuoteRequest::count(),
            "avg_team_size" => (int) round((float) (CustomPlanQuoteRequest::whereNotNull('team_size')->avg('team_size') ?? 0)),
        ];
    }

    public static function list(int $page, int $per_page = 20)
    {
        $requests = CustomPlanQuoteRequest::with([
            'organization:id,name,seats_licensed',
            'requestedBy:id,first_name,last_name,email',
        ])
            ->latest()
            ->paginate($per_page, ['*'], 'page', $page);

        $requests->getCollection()->transform(fn (CustomPlanQuoteRequest $r) => self::row($r));

        return $requests;
    }

    public static function markContacted(int $id): CustomPlanQuoteRequest
    {
        $request = self::find($id);

        if ($request->status === self::STATUS_CONTACTED) {
            throw new InvalidRequestException("This request is already marked as contacted.");
        }

        $request->update([
            'status' => self::STATUS_CONTACTED,
            'contacted_at' => now(),
        ]);

        return $request->refresh();
    }

    private static function find(int $id): CustomPlanQuoteRequest
    {
        $request = CustomPlanQuoteRequest::find($id);
        if (empty($request)) {
            throw new ModelNotFoundException("Request not found.");
        }
        return $request;
    }

    private static function row(CustomPlanQuoteRequest $r): array
    {
        return [
            "id" => $r->id,
            "organization" => $r->organization ? [
                "id" => $r->organization->id,
                "name" => $r->organization->name,
                "seats_licensed" => $r->organization->seats_licensed,
                "active_members" => $r->organization->seatsUsed(),
            ] : null,
            "requested_by" => $r->requestedBy ? trim("{$r->requestedBy->first_name} {$r->requestedBy->last_name}") : null,
            "team_size" => $r->team_size,
            "email" => $r->email,
            "notes" => $r->notes,
            "status" => $r->status,
            "contacted_at" => $r->contacted_at?->toDateTimeString(),
            "created_at" => $r->created_at->toDateTimeString(),
        ];
    }
}
