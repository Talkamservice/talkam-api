<?php

namespace App\Http\Controllers\Api\V2\Post;

use App\Constants\General\ApiConstants;
use App\Constants\General\AppConstants;
use App\Constants\Post\PostCategoryConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Group\GroupResource;
use App\Http\Resources\Post\PostResource;
use App\Http\Resources\Users\UserResource;
use App\Models\GroupMember;
use App\Models\PostCategory;
use App\Models\UserFollow;
use App\Services\Post\SearchService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class SearchController extends Controller
{
    public $search_service;
    function __construct()
    {
        $this->search_service = new SearchService;
    }

    /**
     * v2 search tabs: sort=post|people|groups (v1 values still accepted).
     * People results carry is_following, group results carry is_member,
     * and the post tab includes related_topics chips.
     */
    public function index(Request $request)
    {
        try {
            $filters = $request->all();
            $filters["sort"] = match ($filters["sort"] ?? null) {
                "people" => "user",
                "groups" => "group",
                default => $filters["sort"] ?? null,
            };

            $response = $this->search_service->search($filters);
            $records = $response["records"]->latest()
                ->paginate(AppConstants::API_PAGINATION_SIZE)
                ->appends($request->query());

            $data = collectPagination($records);

            switch ($response["key"]) {
                case "user":
                    $following_ids = UserFollow::where("follower_id", auth()->id())
                        ->pluck("followed_id")->all();
                    $data["data"] = $records->getCollection()->map(function ($user) use ($following_ids, $request) {
                        return array_merge(UserResource::make($user)->resolve($request), [
                            "is_following" => in_array($user->id, $following_ids),
                        ]);
                    });
                    break;
                case "group":
                    $member_group_ids = GroupMember::where("user_id", auth()->id())
                        ->pluck("group_id")->all();
                    $data["data"] = $records->getCollection()->map(function ($group) use ($member_group_ids, $request) {
                        return array_merge(GroupResource::make($group)->resolve($request), [
                            "is_member" => in_array($group->id, $member_group_ids),
                        ]);
                    });
                    break;
                default:
                    $data["data"] = PostResource::collection($records);
                    if ($response["key"] == "post") {
                        $data["related_topics"] = $this->relatedTopics($filters["search"] ?? null);
                    }
            }

            return ApiHelper::validResponse("Search returned successfully", $data);
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $th);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    /**
     * Related Topics chips: the query matched against interest-topic names.
     */
    private function relatedTopics(?string $query): array
    {
        if (empty($query)) {
            return [];
        }

        return PostCategory::where("type", PostCategoryConstants::TYPE_INTEREST_TOPIC)
            ->where("name", "LIKE", "%$query%")
            ->orderBy("name")
            ->get(["id", "name"])
            ->toArray();
    }
}
