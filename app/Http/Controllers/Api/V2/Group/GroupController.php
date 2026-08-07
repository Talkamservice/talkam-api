<?php

namespace App\Http\Controllers\Api\V2\Group;

use App\Constants\General\ApiConstants;
use App\Constants\General\AppConstants;
use App\Constants\General\StatusConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Group\GroupResource;
use App\Services\Group\GroupFollowService;
use App\Services\Group\GroupService;
use App\Services\Group\GroupSuggestionService;
use App\Services\Post\PostStatsService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class GroupController extends Controller
{
    public $group_service;
    public $group_follow_service;
    public $post_stats_service;

    public function __construct()
    {
        $this->group_service = new GroupService;
        $this->group_follow_service = new GroupFollowService;
        $this->post_stats_service = new PostStatsService;
    }

    /**
     * v1 listing; guests only ever see open-access groups.
     */
    public function index(Request $request)
    {
        try {
            $builder = $this->group_service->list($request->all())->whereDoesntHave('promotions');

            if (!auth("sanctum")->check()) {
                $builder = $builder->where("group_access", StatusConstants::OPENED);
            }

            $groups = $builder->paginate(AppConstants::API_PAGINATION_SIZE);
            $data = collectPagination($groups);

            $group_ids = collect($data["data"])->pluck("id")->toArray();
            $this->post_stats_service->saveGroupImpressions($group_ids, ["impressions" => true]);

            $data["data"] = GroupResource::collection($groups);

            return ApiHelper::validResponse("Groups returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function suggested(Request $request)
    {
        try {
            $groups = GroupSuggestionService::suggested(auth()->user())
                ->paginate(AppConstants::API_PAGINATION_SIZE)
                ->appends($request->query());

            $data = collectPagination($groups);
            $data["data"] = GroupResource::collection($groups);

            return ApiHelper::validResponse("Suggested groups returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function followToggle(Request $request)
    {
        try {
            $following = $this->group_follow_service->toggle(auth()->user(), $request->all());
            return ApiHelper::validResponse("Group follow updated successfully", [
                "following" => $following,
            ]);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function following(Request $request)
    {
        try {
            $groups = GroupFollowService::followedGroups(auth()->user())
                ->status()
                ->orderBy("name")
                ->paginate(AppConstants::API_PAGINATION_SIZE)
                ->appends($request->query());

            $data = collectPagination($groups);
            $data["data"] = GroupResource::collection($groups);

            return ApiHelper::validResponse("Followed groups returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
