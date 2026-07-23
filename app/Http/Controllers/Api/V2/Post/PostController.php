<?php

namespace App\Http\Controllers\Api\V2\Post;

use App\Constants\General\ApiConstants;
use App\Constants\General\AppConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Post\PostResource;
use App\Services\Post\PostService;
use App\Services\Post\PostStatsService;
use Illuminate\Http\Request;
use Exception;

class PostController extends Controller
{
    public $post_service;
    public $post_stats_service;

    public function __construct()
    {
        $this->post_service = new PostService;
        $this->post_stats_service = new PostStatsService;
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

            $posts = $this->post_service->list($filters)
                ->with(["comments", "threadNotifications"])
                ->status()
                ->unblocked()
                ->hideGroupPosts()
                ->paginate(AppConstants::API_PAGINATION_SIZE)
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
}
