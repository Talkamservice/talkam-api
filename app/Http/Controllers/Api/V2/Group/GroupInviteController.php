<?php

namespace App\Http\Controllers\Api\V2\Group;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\Group\GroupInviteService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class GroupInviteController extends Controller
{
    public $group_invite_service;
    function __construct()
    {
        $this->group_invite_service = new GroupInviteService;
    }

    public function invite(Request $request, $group)
    {
        try {
            $invite = $this->group_invite_service->invite(auth()->user(), $group, $request->all());
            return ApiHelper::validResponse("Invitation sent successfully", [
                "uuid" => $invite->uuid,
                "invitee_email" => $invite->invitee_email,
                "group_id" => $invite->group_id,
            ]);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (ModelNotFoundException | InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function accept(Request $request)
    {
        try {
            $member = $this->group_invite_service->accept(auth()->user(), $request->all());
            return ApiHelper::validResponse("Invitation accepted successfully", [
                "group_id" => $member->group_id,
                "role" => $member->role,
            ]);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
