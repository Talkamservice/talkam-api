<?php

namespace App\Http\Controllers\Api\V2\Business;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Business\OrganizationInvitationResource;
use App\Http\Resources\Users\UserResource;
use App\Services\Business\OrganizationInviteService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * Team invites — the admin side (send / roster / resend / revoke) and the
 * recipient side (landing / accept, both public).
 */
class InvitationController extends Controller
{
    public OrganizationInviteService $invite_service;

    public function __construct()
    {
        $this->invite_service = new OrganizationInviteService;
    }

    /* ── Admin ──────────────────────────────────────────────────────────── */

    public function index(Request $request)
    {
        try {
            $organization = $request->attributes->get("organization");
            $this->authorize("view", $organization);

            $invitations = OrganizationInviteService::roster($organization, $request->only(["role", "status", "search"]))
                ->paginate($request->input("per_page", ApiConstants::PAGINATION_SIZE_API));

            return ApiHelper::validResponse(
                "Invitations returned successfully",
                OrganizationInvitationResource::collection($invitations)->response()->getData(true)
            );
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    public function store(Request $request)
    {
        try {
            $organization = $request->attributes->get("organization");
            $this->authorize("invite", $organization);

            $created = $this->invite_service->invite($organization, auth()->user(), $request->all());

            return ApiHelper::validResponse("Invites sent successfully", [
                "sent" => count($created),
                "invitations" => OrganizationInvitationResource::collection(collect($created))->resolve(),
                "seats_used" => $organization->refresh()->seatsUsed(),
                "seats_licensed" => (int) $organization->seats_licensed,
            ]);
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    /** Parse-only: returns rows for review; nothing is created until store(). */
    public function import(Request $request)
    {
        try {
            $organization = $request->attributes->get("organization");
            $this->authorize("view", $organization);

            $parsed = $this->invite_service->parseCsv($request->file("file"));

            return ApiHelper::validResponse("Roster parsed successfully", $parsed);
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    public function resend(Request $request, $id)
    {
        try {
            $organization = $request->attributes->get("organization");
            $invitation = OrganizationInviteService::scopedById($organization, $id);
            $this->authorize("resend", $invitation);

            $invitation = $this->invite_service->resend($invitation);

            return ApiHelper::validResponse("Invite resent successfully", [
                "invitation" => OrganizationInvitationResource::make($invitation)->resolve(),
            ]);
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    public function revoke(Request $request, $id)
    {
        try {
            $organization = $request->attributes->get("organization");
            $invitation = OrganizationInviteService::scopedById($organization, $id);
            $this->authorize("revoke", $invitation);

            $invitation = $this->invite_service->revoke($invitation);

            return ApiHelper::validResponse("Invite revoked successfully", [
                "invitation" => OrganizationInvitationResource::make($invitation)->resolve(),
            ]);
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    /* ── Recipient (public) ─────────────────────────────────────────────── */

    public function landing($uuid)
    {
        try {
            return ApiHelper::validResponse(
                "Invite returned successfully",
                OrganizationInviteService::landing($uuid)
            );
        } catch (Exception $e) {
            return $this->failure($e);
        }
    }

    public function accept(Request $request, $uuid)
    {
        try {
            $result = $this->invite_service->accept($uuid, $request->all());
            $user = $result["user"];

            return ApiHelper::validResponse("Welcome to TalkAM", [
                "user" => UserResource::make($user)->resolve(),
                "role" => $result["role"],
                "organization" => [
                    "id" => $result["organization"]->id,
                    "name" => $result["organization"]->name,
                ],
                "token" => $user->createToken("api")->plainTextToken,
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
