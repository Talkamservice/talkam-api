<?php

namespace App\Http\Controllers\Api\V1\User\Group;

use App\Constants\General\ApiConstants;
use App\Constants\General\AppConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Group\GroupMemberResource;
use App\Http\Resources\Group\GroupResource;
use App\Services\Group\GroupMemberService;
use App\Services\Group\GroupService;
use Exception;
use Illuminate\Http\Request;

class GroupMemberController extends Controller
{
    protected $group_service;
    protected $group_member_service;

    public function __construct()
    {
        $this->group_service = new GroupService;
        $this->group_member_service = new GroupMemberService;
    }

    public function index(Request $request, $id)
    {
        try {
            $group = $this->group_service->getById($id);
            $members = $this->group_member_service->list($group->id, $request->all())->status()->latest("name")->appends($request->query());
            $data = GroupMemberResource::make($members);
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
            $group = $this->group_service->getById($id);
            $data = GroupResource::make($group);
            return ApiHelper::validResponse("Group details returned successfully", $data);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function members($id)
    {

    }
    public function store(Request $request)
    {
        try {
            $group = $this->group_service->create($request->all());
            $data = GroupResource::make($group);
            return ApiHelper::validResponse("Group created successfully", $data);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null , $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $group = $this->group_service->update($request->all(), $id);
            $data = GroupResource::make($group);
            return ApiHelper::validResponse("Group updated successfully", $data);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function destroy($id)
    {
        try {
            $group = $this->group_service->getById($id);
            $group->delete();
            return ApiHelper::validResponse("Group destroyed successfully");
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse("Something went wrong while trying to process your request", ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }
}
