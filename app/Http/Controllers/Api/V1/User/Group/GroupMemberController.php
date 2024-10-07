<?php

namespace App\Http\Controllers\Api\V1\User\Group;

use App\Constants\Account\User\UserConstants;
use App\Constants\General\ApiConstants;
use App\Constants\General\AppConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Group\GroupMemberResource;
use App\Http\Resources\Group\GroupResource;
use App\Notifications\Group\RemoveGroupMemberNotification;
use App\Services\Group\GroupMemberService;
use App\Services\Group\GroupService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class GroupMemberController extends Controller
{
    protected $group_service;
    protected $group_member_service;

    public function __construct()
    {
        $this->group_service = new GroupService;
        $this->group_member_service = new GroupMemberService;
    }

    public function index(Request $request)
    {
        try {
            $group = $this->group_service->getById($request->group_id);
            $data = $this->group_member_service->listByGroup($group->id, $request->all());
            return ApiHelper::validResponse("Group members returned successfully", $data);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function show($id)
    {
        try {
            $group = $this->group_member_service->getById($id);
            $data = GroupMemberResource::make($group);
            return ApiHelper::validResponse("Group details returned successfully", $data);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function store(Request $request)
    {
        try {
            $group = $this->group_member_service->create($request->all());
            $data = GroupMemberResource::make($group);
            return ApiHelper::validResponse("Group followed successfully", $data);
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $th);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $group = $this->group_member_service->update($request->all(), $id);
            $data = GroupMemberResource::make($group);
            return ApiHelper::validResponse("Member updated successfully", $data);
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $th);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function unfollow(Request $request)
    {
        try {
            $group = $this->group_member_service->removeByUserId($request->all());
            return ApiHelper::validResponse("Group unfollowed successfully");
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $th);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function following(Request $request)
    {
        try {
            $groups = $this->group_service->following($request->all())
                ->status()->paginate(AppConstants::API_PAGINATION_SIZE)
                ->appends($request->query());
            $data = collectPagination($groups);
            $data["data"] = GroupResource::collection($data["data"]);
            return ApiHelper::validResponse("Groups returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function destroy($id)
    {
        try {
            $member = $this->group_member_service->getById($id);

            if ($member->role == UserConstants::OWNER) {
                throw new ModelNotFoundException("You cannot remove the owner of the group");
            }
            Notification::send($member->user, new RemoveGroupMemberNotification($member));
            $member->delete();
            return ApiHelper::validResponse("Member removed successfully");
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function requestAccess($id)
    {
        try {
            $this->group_service->requestAccess($id);
            return ApiHelper::validResponse("Request sent successfully");
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $th);
        } catch (ModelNotFoundException | InvalidRequestException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function updateAccessRequest(Request $request)
    {
        try {
            $this->group_service->updateAccessRequest($request->all());
            return ApiHelper::validResponse("Request updated successfully");
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $th);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function list(Request $request)
    {
        try {
            $group = $this->group_service->getById($request->group_id);
            $group_members = $this->group_member_service->list($group->id, $request->all())->paginate(AppConstants::API_PAGINATION_SIZE);
            $data = collectPagination($group_members);
            $data["data"] = GroupMemberResource::collection($data["data"]);
            return ApiHelper::validResponse("Group members returned successfully", $data);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function reportMember(Request $request)
    {
        try {
            $this->group_member_service->reportMember($request->all());
            return ApiHelper::validResponse("Report sent successfully");
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $th);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function suspendMember(Request $request, $id)
    {
        try {
            $group_member = $this->group_member_service->suspendMember($id);
            $status = $group_member->status == StatusConstants::SUSPENDED ? strtolower(StatusConstants::SUSPENDED) : strtolower(StatusConstants::UNSUSPENDED);
            return ApiHelper::validResponse("Group member has been $status successfully");
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $th);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }
}
