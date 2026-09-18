<?php

namespace App\Http\Controllers\Api\V2\Post;

use App\Constants\General\ApiConstants;
use App\Constants\General\AppConstants;
use App\Constants\General\StatusConstants;
use App\Constants\Post\PostCategoryConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Post\PostResource;
use App\Services\Post\NotInterestService;
use App\Services\Post\PostService;
use App\Services\Post\PostStatsService;
use App\Services\User\FollowService;
use App\Services\User\MuteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Exception;

class PostController extends Controller
{
    public $post_service;
    public $post_stats_service;
    public $not_interest_service;

    public function __construct()
    {
        $this->post_service = new PostService;
        $this->post_stats_service = new PostStatsService;
        $this->not_interest_service = new NotInterestService;
    }

    /**
     * v2 feed. tab=for_you → interests-first feed (new PostService branch);
     * tab=trending → engagement ranking (v1's "featured" logic — the v2
     * design's Trending is engagement-based, not tag-matched).
     */
    public function index(Request $request)
    {
        try {
            $filters = $request->all();
            if (($filters["tab"] ?? null) == "trending") {
                $filters["tab"] = "featured";
            }

            $builder = $this->post_service->list($filters)
                ->with(["comments", "threadNotifications"])
                ->status()
                ->unblocked()
                ->hideGroupPosts();

            // Profile Posts tab (§09): anonymous posts are own-view only.
            if (!empty($key = $filters["user_id"] ?? null)) {
                $viewer = auth()->user();
                $is_self = is_numeric($key)
                    ? $viewer->id == $key
                    : $viewer->username == $key;
                if (!$is_self) {
                    $builder = $builder->anonymous(0);
                }
            }

            // v2-only exclusions: muted authors and not-interested posts
            // (v1 queries untouched — mute/not-interested are feeds-only).
            $muted_ids = MuteService::mutedIds(auth()->user());
            if (!empty($muted_ids)) {
                $builder = $builder->whereNotIn("user_id", $muted_ids);
            }
            $not_interested_ids = NotInterestService::notInterestedIds(auth()->user());
            if (!empty($not_interested_ids)) {
                $builder = $builder->whereNotIn("id", $not_interested_ids);
            }

            $posts = $builder->paginate(AppConstants::API_PAGINATION_SIZE)
                ->appends($request->query());

            $data = collectPagination($posts);

            $post_ids = $data["data"]?->pluck("id")?->toArray() ?? [];
            $this->post_stats_service->savePostImpressions($post_ids, ["impressions" => true]);

            $data["data"] = PostResource::collection($posts);

            return ApiHelper::validResponse("Posts returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    /**
     * All posts by a specific user (id or username) — the same feed query
     * `index` already runs for `?user_id=`, just addressed by path instead
     * of query string. Same visibility rules apply (own anonymous posts are
     * only visible to their author, blocked/muted authors excluded, etc).
     */
    public function byUser(Request $request, $id)
    {
        $request->merge(["user_id" => $id]);
        return $this->index($request);
    }

    /**
     * v2 create post: title required, at least one tag, body capped at 500
     * chars, and the category must be an interest topic (server-side check).
     * v1's looser rules stay untouched for v1 clients.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "title" => "required|string|max:150",
                "body" => "nullable|string|max:500",
                "tags" => "required|array|min:1",
                "category_id" => [
                    "required",
                    "numeric",
                    Rule::exists("post_categories", "id")
                        ->where("type", PostCategoryConstants::TYPE_INTEREST_TOPIC),
                ],
            ], [
                "tags.min" => "Add at least one tag",
                "category_id.exists" => "The selected category must be an interest topic",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $post = $this->post_service->create($request->all());

            if ($post->status == StatusConstants::ACTIVE) {
                FollowService::notifyFollowersOfNewPost($post);
            }

            $data = PostResource::make($post);
            return ApiHelper::validResponse("Post created successfully", $data);
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function notInterested(Request $request)
    {
        try {
            $marked = $this->not_interest_service->toggle(auth()->user(), $request->all());
            return ApiHelper::validResponse("Post preference updated successfully", [
                "not_interested" => $marked,
            ]);
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }
}
