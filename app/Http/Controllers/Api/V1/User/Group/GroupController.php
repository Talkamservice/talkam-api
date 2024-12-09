<?php

namespace App\Http\Controllers\Api\V1\User\Group;

use App\Constants\General\ApiConstants;
use App\Constants\General\AppConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Group\GroupMemberResource;
use App\Http\Resources\Group\GroupResource;
use App\Http\Resources\Group\PromotedGroupResource;
use App\Services\Group\GroupService;
use App\Services\Post\PostStatsService;
use App\Services\Post\RecentViewService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Validation\ValidationException;

class GroupController extends Controller
{
    protected $group_service;
    protected $post_stats_service;
    protected $recent_view_service;

    public function __construct()
    {
        $this->group_service = new GroupService;
        $this->recent_view_service = new RecentViewService;
        $this->post_stats_service = new PostStatsService;
    }

    public function index(Request $request)
    {
        try {
            $groups = $this->group_service->list($request->all())->paginate(AppConstants::API_PAGINATION_SIZE);
            $data = collectPagination($groups);
    
            // Collect the group ids for impressions tracking
            $group_ids = collect($data["data"])->pluck("id")->toArray();
            $this->post_stats_service->saveGroupImpressions($group_ids, ["impressions" => true]);

            // Transform with resource
            $data["data"] = GroupResource::collection($groups);
    
            return ApiHelper::validResponse("Groups returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
    
    public function getPromoteGroups(Request $request)
    {
        try {
            $groups = $this->group_service->getByPromotedGroups($request->all())->paginate(AppConstants::API_PAGINATION_SIZE);
            $data = collectPagination($groups);
    
            // Collect the group ids for impressions tracking
            $group_ids = collect($data["data"])->pluck("id")->toArray();
            $this->post_stats_service->saveGroupImpressions($group_ids, ["impressions" => true]);

            // Transform with resource
            $data["data"] = PromotedGroupResource::collection($groups);
    
            return ApiHelper::validResponse("Promoted Groups returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function show($id)
    {
        try {
            $field = is_numeric($id) ? "id" : "uuid";
            $group = $this->group_service->getById($id, $field);
            $this->recent_view_service->create(["group_id" => $group->id]);
            $this->post_stats_service->dispatch(["group_id" => $group->id, "clicks" => true]);
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
        try {
            $group = $this->group_service->getById($id);
            $data = GroupMemberResource::make($group->members);
            return ApiHelper::validResponse("Group members returned successfully", $data);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function store(Request $request)
    {
        try {
            $group = $this->group_service->create($request->all());
            $data = GroupResource::make($group);
            return ApiHelper::validResponse("Group created successfully", $data);
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse("The given data is invalid", ApiConstants::VALIDATION_ERR_CODE, null , $th);
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
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse("The given data is invalid", ApiConstants::VALIDATION_ERR_CODE, null , $th);
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
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }
}
