<?php

namespace App\Http\Controllers\Api\V2\PlatformAdmin;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\PlatformAdmin\PlatformCommunityService;
use App\Services\Report\Post\CommentReportService;
use App\Services\Report\Post\PostReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Exception;

class PlatformCommunityController extends Controller
{
    public function overview()
    {
        try {
            return ApiHelper::validResponse("Overview returned successfully", PlatformCommunityService::overview());
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function postReports(Request $request)
    {
        try {
            $page = max(1, (int) $request->input('page', 1));
            return ApiHelper::validResponse("Post reports returned successfully", PlatformCommunityService::postReports($page));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function resolvePostReport($id)
    {
        try {
            $report = (new PostReportService)->changeStatus(['action' => 'Resolved'], $id);
            return ApiHelper::validResponse("Report resolved", $report);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function deletePostReport($id)
    {
        try {
            PostReportService::delete($id);
            return ApiHelper::validResponse("Reported post deleted", []);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function commentReports(Request $request)
    {
        try {
            $page = max(1, (int) $request->input('page', 1));
            return ApiHelper::validResponse("Comment reports returned successfully", PlatformCommunityService::commentReports($page));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function resolveCommentReport($id)
    {
        try {
            $report = (new CommentReportService)->changeStatus(['action' => 'Resolved'], $id);
            return ApiHelper::validResponse("Report resolved", $report);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function deleteCommentReport($id)
    {
        try {
            CommentReportService::delete($id);
            return ApiHelper::validResponse("Reported comment deleted", []);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function groupReports(Request $request)
    {
        try {
            $page = max(1, (int) $request->input('page', 1));
            return ApiHelper::validResponse("Group reports returned successfully", PlatformCommunityService::groupReports($page));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function resolveGroupReport($id)
    {
        try {
            return ApiHelper::validResponse("Report resolved", PlatformCommunityService::resolveGroupReport((int) $id));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function suspendGroup(Request $request, $groupId)
    {
        try {
            $validator = Validator::make($request->all(), [
                "reason" => "required|string|max:1000",
                "suspend_until" => "required|date|after:today",
            ]);
            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
            $data = $validator->validated();
            $message = PlatformCommunityService::suspendGroup((int) $groupId, $data['reason'], $data['suspend_until']);
            return ApiHelper::validResponse($message, []);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function banGroup(Request $request, $groupId)
    {
        try {
            $validator = Validator::make($request->all(), [
                "reason" => "required|string|max:1000",
            ]);
            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
            $message = PlatformCommunityService::banGroup((int) $groupId, $validator->validated()['reason']);
            return ApiHelper::validResponse($message, []);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function reactivateGroup($groupId)
    {
        try {
            $message = PlatformCommunityService::reactivateGroup((int) $groupId);
            return ApiHelper::validResponse($message, []);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function deleteGroup($groupId)
    {
        try {
            $message = PlatformCommunityService::deleteGroup((int) $groupId);
            return ApiHelper::validResponse($message, []);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function categories()
    {
        try {
            return ApiHelper::validResponse("Categories returned successfully", PlatformCommunityService::categories());
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function createGroup(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "name" => "required|string|max:255",
                "category_id" => "required|exists:post_categories,id",
                "description" => "nullable|string|max:2000",
                "owner_email" => "required|email",
                "group_access" => "nullable|string|in:Opened,Closed,Approval",
            ]);
            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
            $group = PlatformCommunityService::createGroup($validator->validated());
            return ApiHelper::validResponse("Group created", $group);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function groupMembers(Request $request, $groupId)
    {
        try {
            $page = max(1, (int) $request->input('page', 1));
            return ApiHelper::validResponse("Members returned successfully", PlatformCommunityService::groupMembers((int) $groupId, $page));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function updateGroupMemberRole(Request $request, $memberId)
    {
        try {
            $validator = Validator::make($request->all(), [
                "role" => "required|string",
            ]);
            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
            $member = PlatformCommunityService::updateGroupMemberRole((int) $memberId, $validator->validated()['role']);
            return ApiHelper::validResponse("Role updated", $member);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function suspendGroupMember($memberId)
    {
        try {
            $member = PlatformCommunityService::suspendGroupMember((int) $memberId);
            return ApiHelper::validResponse($member->status === "Suspended" ? "Member suspended" : "Member unsuspended", $member);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function allGroups(Request $request)
    {
        try {
            $page = max(1, (int) $request->input('page', 1));
            $filters = $request->only(['search', 'status', 'category_id']);
            return ApiHelper::validResponse("Groups returned successfully", PlatformCommunityService::allGroups($filters, $page));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function groupDetail($groupId)
    {
        try {
            return ApiHelper::validResponse("Group returned successfully", PlatformCommunityService::groupDetail((int) $groupId));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function groupReportsForGroup(Request $request, $groupId)
    {
        try {
            $page = max(1, (int) $request->input('page', 1));
            return ApiHelper::validResponse("Group reports returned successfully", PlatformCommunityService::groupReportsForGroup((int) $groupId, $page));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function commentReportsForGroup(Request $request, $groupId)
    {
        try {
            $page = max(1, (int) $request->input('page', 1));
            return ApiHelper::validResponse("Comment reports returned successfully", PlatformCommunityService::commentReportsForGroup((int) $groupId, $page));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function updateGroup(Request $request, $groupId)
    {
        try {
            $validator = Validator::make($request->all(), [
                "name" => "required|string|max:255",
                "category_id" => "nullable|exists:post_categories,id",
                "description" => "nullable|string|max:2000",
                "group_access" => "nullable|string|in:Opened,Closed,Approval",
            ]);
            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
            $group = PlatformCommunityService::updateGroup((int) $groupId, $validator->validated());
            return ApiHelper::validResponse("Group updated", $group);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function addGroupMember(Request $request, $groupId)
    {
        try {
            $validator = Validator::make($request->all(), [
                "email" => "required|email",
                "role" => "required|string",
            ]);
            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
            $data = $validator->validated();
            $member = PlatformCommunityService::addGroupMember((int) $groupId, $data['email'], $data['role']);
            return ApiHelper::validResponse("Member added", $member);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function removeGroupMember($memberId)
    {
        try {
            PlatformCommunityService::removeGroupMember((int) $memberId);
            return ApiHelper::validResponse("Member removed", []);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function groupMemberReports(Request $request)
    {
        try {
            $page = max(1, (int) $request->input('page', 1));
            return ApiHelper::validResponse("Member reports returned successfully", PlatformCommunityService::groupMemberReports($page));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function resolveGroupMemberReport($id)
    {
        try {
            return ApiHelper::validResponse("Report resolved", PlatformCommunityService::resolveGroupMemberReport((int) $id));
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function suspendReportedMember(Request $request, $reportId)
    {
        try {
            $validator = Validator::make($request->all(), [
                "reason" => "required|string|max:1000",
                "suspend_until" => "required|date|after:today",
            ]);
            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
            $data = $validator->validated();
            $message = PlatformCommunityService::suspendOrBanReportedMember((int) $reportId, $data['reason'], $data['suspend_until']);
            return ApiHelper::validResponse($message, []);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }

    public function unsuspendReportedMember($groupMemberId)
    {
        try {
            $message = PlatformCommunityService::unsuspendReportedMember((int) $groupMemberId);
            return ApiHelper::validResponse($message, []);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($e->getMessage() ?: $this->serverErrorMessage, ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        }
    }
}
