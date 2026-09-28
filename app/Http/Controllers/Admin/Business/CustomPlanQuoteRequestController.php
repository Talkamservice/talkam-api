<?php

namespace App\Http\Controllers\Admin\Business;

use App\Constants\General\AppConstants;
use App\Constants\General\NotificationConstants;
use App\Http\Controllers\Controller;
use App\Models\CustomPlanQuoteRequest;
use Illuminate\Http\Request;

/**
 * Wellbeing Plus (custom pricing) leads from the Billing screen's Compare
 * view. Read + "mark contacted" only — there is no create/edit here, these
 * are submitted by organisations, not admins.
 */
class CustomPlanQuoteRequestController extends Controller
{
    public function index(Request $request)
    {
        $requests = CustomPlanQuoteRequest::with(["organization", "requestedBy"])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas("organization", fn ($oq) => $oq->where("name", "like", "%{$search}%"))
                        ->orWhere("email", "like", "%{$search}%");
                });
            })
            ->latest()
            ->paginate(AppConstants::ADMIN_PAGINATION_SIZE);

        return view("dashboards.admin.pages.business.custom-plan-quote-request.index", [
            "sn" => $requests->firstItem(),
            "requests" => $requests,
        ]);
    }

    public function markContacted($id)
    {
        $request = CustomPlanQuoteRequest::findOrFail($id);
        $request->update(["status" => "contacted", "contacted_at" => now()]);

        return redirect()->back()
            ->with(NotificationConstants::SUCCESS_MSG, "Marked as contacted");
    }
}
