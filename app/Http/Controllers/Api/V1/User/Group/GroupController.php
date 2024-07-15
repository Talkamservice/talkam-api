<?php

namespace App\Http\Controllers\Api\V1\User\Group;

use App\Constants\General\ApiConstants;
use App\Constants\General\AppConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Post\PostResource;
use App\Http\Resources\Post\TrendingResource;
use App\Services\Group\GroupService;
use App\Services\Post\PostService;
use App\Services\Post\RecentViewService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GroupController extends Controller
{
    protected $group_service;

    public function __construct()
    {
        $this->group_service = new GroupService;
    }

    public function index(Request $request)
    {
        try {
            $posts = $this->group_service->list($request->all())->status()->unblocked()->inRandomOrder()->paginate(AppConstants::API_PAGINATION_SIZE)->appends($request->query());
            $data = collectPagination($posts);
            $data["data"] = PostResource::collection($data["data"]);
            return ApiHelper::validResponse("Posts returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function index(Request $request)
    {
        try {
            $groups = GroupQueryBuilder::filterList($request)->where("parish_id", $parish_id)->get();
            $data = GroupResource::collection($groups);
            return ApiHelper::validResponse("Groups returned successfully", $data);
        } catch (Exception $th) {
            //throw $th;
            return ApiHelper::problemResponse("Something went wrong while trying to process your request", ApiConstants::SERVER_ERR_CODE, $th);
        }
    }
    public function show($id)
    {
        try {
            $group = GroupService::getById($id);
            $data = GroupResource::make($group);
            return ApiHelper::validResponse("Group details returned successfully", $data);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse("Something went wrong while trying to process your request", ApiConstants::SERVER_ERR_CODE, $th);
        }
    }
    public function create(Request $request)
    {
        try {
            $group = $this->group_registration_service->create($request->all());
            $data = GroupResource::make($group);
            return ApiHelper::validResponse("Group created successfully", $data);
        } catch (GroupException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, $th);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse("Something went wrong while trying to process your request", ApiConstants::SERVER_ERR_CODE, $th);
        }
    }

    public function update(Request $request, Parish $parish, $id)
    {
        try {
            $group = $this->group_service->update($request->all(), $id);
            $data = GroupResource::make($group);
            return ApiHelper::validResponse("Group updated successfully", $data);
        } catch (GroupException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, $th);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse("Something went wrong while trying to process your request", ApiConstants::SERVER_ERR_CODE, $th);
        }
    }

    public function destroy(Parish $parish, $id)
    {
        try {
            $group = $this->group_service->getById($id);
            $group->delete();
            return ApiHelper::validResponse("Group destroyed successfully");
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse("Something went wrong while trying to process your request", ApiConstants::SERVER_ERR_CODE, $th);
        }
    }
}
