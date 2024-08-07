<?php

namespace App\Http\Controllers\Api\V1\User\Group;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Group\GroupMemberResource;
use App\Services\Group\GroupMemberService;
use App\Services\Group\GroupService;
use Exception;
use Illuminate\Http\Request;
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
            return ApiHelper::validResponse("Group created successfully", $data);
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $th);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null , $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $group = $this->group_member_service->update($request->all(), $id);
            $data = GroupMemberResource::make($group);
            return ApiHelper::validResponse("Group updated successfully", $data);
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
            return ApiHelper::problemResponse("Something went wrong while trying to process your request", ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function destroy($id)
    {
        try {
            $group = $this->group_member_service->getById($id);
            $group->delete();
            return ApiHelper::validResponse("Group deleted successfully");
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse("Something went wrong while trying to process your request", ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function requestAccess($id)
    {
        try {
             $this->group_service->requestAccess($id);
            return ApiHelper::validResponse("Request sent successfully");
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $th);
        } catch (ModelNotFoundException $th) {
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
}
