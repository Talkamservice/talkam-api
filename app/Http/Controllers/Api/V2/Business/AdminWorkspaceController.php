<?php

namespace App\Http\Controllers\Api\V2\Business;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Business\OrganizationResource;
use App\Services\Business\OrgAdminService;
use App\Services\Business\OrgRosterService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * Seat administration, the therapist network, trust & safety, the activity log
 * and company settings.
 *
 * The roster served here is CONTRACT data only — who holds a seat, where, and
 * at what status. It deliberately carries no session count and no last-active
 * timestamp; see planning-docs/web-api/03-admin-dashboard.md §0.
 */
class AdminWorkspaceController extends Controller
{
    public OrgRosterService $roster_service;
    public OrgAdminService $admin_service;

    public function __construct()
    {
        $this->roster_service = new OrgRosterService;
        $this->admin_service = new OrgAdminService;
    }

    /* ── Employees ──────────────────────────────────────────────────────── */

    public function employees(Request $request)
    {
        try {
            $organization = $request->attributes->get("organization");

            return ApiHelper::validResponse("Employees returned successfully", [
                "employees" => OrgRosterService::roster($organization, $request->only(["department", "status", "search"])),
                "departments" => OrgRosterService::departments($organization),
                "seats_used" => $organization->seatsUsed(),
                "seats_licensed" => (int) $organization->seats_licensed,
            ]);
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    /** CSV export — exactly the roster columns, no usage data. */
    public function exportEmployees(Request $request)
    {
        try {
            $organization = $request->attributes->get("organization");
            $rows = OrgRosterService::roster($organization, $request->only(["department", "status", "search"]));

            return response()->streamDownload(function () use ($rows) {
                $out = fopen("php://output", "w");
                fputcsv($out, ["ID", "Email", "Department", "Role", "Status", "Activated"]);

                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row["id"],
                        $row["email"],
                        $row["department"],
                        $row["role"],
                        $row["status"],
                        $row["activated_at"],
                    ]);
                }

                fclose($out);
            }, "talkam-employees-" . now()->format("Y-m-d") . ".csv", ["Content-Type" => "text/csv"]);
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    public function deactivateEmployee(Request $request, $member)
    {
        try {
            $organization = $request->attributes->get("organization");
            $row = $this->roster_service->deactivate($organization, $member);

            return ApiHelper::validResponse("Seat deactivated successfully", [
                "id" => OrgRosterService::displayId($row->id),
                "status" => $row->status,
                "seats_used" => $organization->refresh()->seatsUsed(),
            ]);
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    public function reactivateEmployee(Request $request, $member)
    {
        try {
            $organization = $request->attributes->get("organization");
            $row = $this->roster_service->reactivate($organization, $member);

            return ApiHelper::validResponse("Seat reactivated successfully", [
                "id" => OrgRosterService::displayId($row->id),
                "status" => $row->status,
                "seats_used" => $organization->refresh()->seatsUsed(),
            ]);
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    /* ── Therapist network ──────────────────────────────────────────────── */

    public function therapists(Request $request)
    {
        try {
            return ApiHelper::validResponse(
                "Therapists returned successfully",
                OrgRosterService::therapists(
                    $request->attributes->get("organization"),
                    $request->only(["specialty", "bench_only"])
                )
            );
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    /* ── Trust & safety / activity / settings ───────────────────────────── */

    public function safetyReports(Request $request)
    {
        try {
            return ApiHelper::validResponse("Safety reports returned successfully", [
                "reports" => OrgAdminService::safetyReports($request->attributes->get("organization")),
            ]);
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    public function activity(Request $request)
    {
        try {
            return ApiHelper::validResponse("Activity returned successfully", [
                "activity" => OrgAdminService::activity($request->attributes->get("organization")),
            ]);
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    public function updateProfile(Request $request)
    {
        try {
            $organization = $request->attributes->get("organization");
            $this->authorize("update", $organization);

            $organization = $this->admin_service->updateProfile($organization, $request->all());

            return ApiHelper::validResponse("Company profile updated successfully", [
                "organization" => OrganizationResource::make($organization)->resolve(),
            ]);
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    public function uploadLogo(Request $request)
    {
        try {
            $organization = $request->attributes->get("organization");
            $this->authorize("update", $organization);

            $organization = $this->admin_service->uploadLogo($organization, $request->all());

            return ApiHelper::validResponse("Company logo updated successfully", [
                "organization" => OrganizationResource::make($organization)->resolve(),
            ]);
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    private function failure(Exception $e)
    {
        if ($e instanceof ValidationException) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        }

        if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
            return ApiHelper::problemResponse("You do not have permission to perform this action.", ApiConstants::FORBIDDEN_ERR_CODE, null, null);
        }

        if ($e instanceof ModelNotFoundException) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, null);
        }

        if ($e instanceof InvalidRequestException) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, null);
        }

        return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
    }
}
