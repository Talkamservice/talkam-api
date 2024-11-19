<?php

namespace App\Services\Post;

use App\Constants\General\AppConstants;
use App\Constants\General\StatusConstants;
use App\Constants\Post\PostConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\MethodsHelper;
use App\Models\MergeMedia;
use App\Models\Post;
use App\Models\PostAttachment;
use App\Models\PostComment;
use App\Models\Promotion;
use App\Models\TrendingTag;
use App\Services\Post\PostAttachmentService;
use App\Services\Post\PostPollService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PostService
{
    public $post_attachment_service;
    public $post_poll_service;
    public $user;
    public $post_schedule_service;

    public function __construct()
    {
        $this->post_poll_service = new PostPollService;
        $this->post_schedule_service = new PostScheduleService;
        $this->post_attachment_service = new PostAttachmentService;
    }

    public static function getById($id): Post
    {
        $post = Post::find($id);
        if (empty($post)) {
            throw new ModelNotFoundException("Post not found");
        }
        return $post;
    }

    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    public static function validate($data, $id = null)
    {
        $validator = Validator::make($data, [
            "category_id" => "nullable|numeric|exists:post_categories,id|" . Rule::requiredIf(empty($id)),
            "group_id" => "nullable|numeric|exists:groups,id",
            "type" => "required|string|" . Rule::in(PostConstants::TYPES),
            "title" => "nullable|string",
            "body" => "nullable|string",
            "status" => "nullable|string|" . Rule::in(StatusConstants::POST_STATUS_OPTIONS),
            "cover" => "string|nullable",
            "publish_at" => "nullable",
            "tags" => "nullable",
            "is_anonymous" => "nullable|in:0,1|" . Rule::in(array_keys(StatusConstants::BOOL_OPTIONS)),
            "can_comment" => "nullable|in:0,1|" . Rule::in(array_keys(StatusConstants::BOOL_OPTIONS)),
            "attachments" => "nullable|array|" . Rule::requiredIf(in_array($data["type"], [PostConstants::FILE, PostConstants::IMAGE, PostConstants::VIDEO])),
            "attachments*.url" => "nullable|string|" . Rule::requiredIf(in_array($data["type"], [PostConstants::FILE, PostConstants::IMAGE, PostConstants::VIDEO])),
            "attachments*.type" => "nullable|string|" . Rule::requiredIf(in_array($data["type"], [PostConstants::FILE, PostConstants::IMAGE, PostConstants::VIDEO])),
            "poll" => "nullable|array|" . Rule::requiredIf($data["type"] == PostConstants::POLL),
            "poll.type" => "nullable|string|" . Rule::requiredIf($data["type"] == PostConstants::POLL),
            "poll.duration" => "nullable|numeric|" . Rule::requiredIf($data["type"] == PostConstants::POLL),
            "poll.options" => "nullable|array|" . Rule::requiredIf($data["type"] == PostConstants::POLL),
            'poll.options.*' => "nullable|string|" . Rule::requiredIf($data["type"] == PostConstants::POLL),
        ], [
            "cover.required" => "The cover image url is required",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public function create(array $data)
    {
        DB::beginTransaction();
        try {
            $data = self::validate($data);
            $user = $this->user ?? auth()->user();

            $data = array_merge([
                "uuid" => self::getUuid(),
                "user_id" => $user->id,
                "status" => $data["status"] ?? StatusConstants::ACTIVE,
                "type" => $data["type"]
            ], $data);

            // JSON encode the tags field if it is set
            if (isset($data['tags']) && is_array($data['tags'])) {
                $data['tags'] = json_encode($data['tags']);
            }

            $attachments = $data["attachments"] ?? null;
            $poll = $data["poll"] ?? null;
            unset($data["poll"], $data["attachments"]);

            $post = Post::create($data);

            if (isset($attachments)) {
                foreach ($attachments as $key => $attachment) {
                    $this->post_attachment_service->create([
                        'post_id' => $post->id,
                        'user_id' => $user->id,
                        ...$attachment
                    ]);
                }
            }

            if (isset($poll)) {
                $this->post_poll_service->create([
                    "post_id" => $post->id,
                    ...$poll
                ]);
            }

            if (!empty($post->publish_at) && !in_array($post->status, [StatusConstants::DRAFTED])) {
                $this->post_schedule_service->addToSchedule($post);
            }

            if ($post->is_anonymous == 1) {
                $post->user->increment("anonymous_post");
            }

            DB::commit();
            return $post;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function update(array $data, $id)
    {
        $data = self::validate($data, $id);
        $post = self::getById($id);

        // JSON encode the tags field if it is set
        if (isset($data['tags']) && is_array($data['tags'])) {
            $data['tags'] = json_encode($data['tags']);
        }

        $attachments = $data["attachments"] ?? null;
        $poll = $data["poll"] ?? null;
        unset($data["poll"], $data["attachments"]);
        $post->update($data);

        if (isset($attachments)) {
            $post->attachments()->delete();
            foreach ($attachments as $key => $attachment) {
                $this->post_attachment_service->create([
                    "post_id" => $post->id,
                    "user_id" => $post->user_id,
                    ...$attachment
                ]);
            }
        }


        if (isset($poll)) {
            $post->polls()->delete();
            $this->post_poll_service->create([
                "post_id" => $post->id,
                ...$poll
            ]);
        }

        return $post->refresh();
    }

    public static function delete($post_id)
    {
        $post = self::getById($post_id);
        $post->delete();
    }

    public static function getUuid()
    {
        $code = strtoupper(MethodsHelper::getRandomToken(6));
        if (Post::where("uuid", $code)->count() > 0) {
            return self::getUuid();
        }
        return $code;
    }
 
    public static function list(array $data = [])
    {
        $builder = Post::with("user");
    
        // Apply filters as before
        if (!empty($key = $data["search"] ?? null)) {
            $builder = $builder->search($key);
        }
    
        if (!empty($key = $data["category_id"] ?? null)) {
            $builder = $builder->where("category_id", $key);
        }
    
        if (!empty($key = $data["status"] ?? null)) {
            $builder = $builder->where("status", $key); // Apply status filter
        }
    
        if (!empty($key = $data["type"] ?? null)) {
            $builder = $builder->where("type", $key);
        }
    
        // Apply unblocked and hide group posts filters before fetching the data
        $builder = $builder->unblocked()->hideGroupPosts();
    
        // Fetch posts and paginate
        $posts = $builder->paginate(AppConstants::API_PAGINATION_SIZE);
    
        // Process and interleave posts
        $regularPosts = $posts->items(); // Get the items of the paginated result
        $interleavedPosts = self::interleavePromotedPosts($regularPosts);
    
        // Now, wrap the interleaved posts into a LengthAwarePaginator
        $paginatedData = new \Illuminate\Pagination\LengthAwarePaginator(
            $interleavedPosts, // Interleaved posts
            $posts->total(), // Total count of posts
            $posts->perPage(), // Items per page
            $posts->currentPage(), // Current page
            ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()] // Path for pagination links
        );
    
        return $paginatedData; // Return paginated interleaved posts
    }
    
    public static function interleavePromotedPosts($regularPosts)
    {
        // Fetch only promotions that have a valid post_id (not null) and exclude those with group_id
        $promotedPosts = Promotion::whereNotNull('post_id') // Only include promotions with a post_id
            ->whereNull('group_id') // Exclude promotions with a group_id
            ->with('post') // Eager load the related post
            ->get()
            ->filter(function ($promotion) {
                // Filter out promotions that have expired based on the 'expires_at' attribute
                return $promotion->expires_at->greaterThanOrEqualTo(now());
            })
            ->sortByDesc(function ($promotion) {
                return $promotion->cost;
            })
            ->pluck('post'); // This will be a collection of post models
    
        $interleavedPosts = [];
        $regularPostIndex = 0;
        $promotedPostIndex = 0;
        $regularPostInterval = 5; // Show 5 regular posts between promoted posts
    
        // First, add the first promoted post if available
        if ($promotedPostIndex < $promotedPosts->count()) {
            $interleavedPosts[] = $promotedPosts[$promotedPostIndex];
            $promotedPostIndex++;
        }
    
        // Now, interleave regular posts with promoted posts
        while ($regularPostIndex < count($regularPosts)) {
            // Add 5 regular posts
            for ($i = 0; $i < $regularPostInterval && $regularPostIndex < count($regularPosts); $i++) {
                $interleavedPosts[] = $regularPosts[$regularPostIndex];
                $regularPostIndex++;
            }
    
            // After 5 regular posts, add the next promoted post if available
            if ($promotedPostIndex < $promotedPosts->count()) {
                $interleavedPosts[] = $promotedPosts[$promotedPostIndex];
                $promotedPostIndex++;
            }
        }
    
        return $interleavedPosts;
    }
    
    
    public static function trends(array $data = [])
{
    // Build the initial query with the latest scope and status scope
    $builder = TrendingTag::latest()->status();

   // Fetch paginated posts (this logic will stay in place)
    $posts = $builder->paginate(AppConstants::API_PAGINATION_SIZE);

    // Get the regular posts (items)
    $regularPosts = $posts->items();

    // Interleave promoted posts if needed
    $interleavedPosts = self::interleavePromotedPosts($regularPosts);

    // Create a paginated result with the interleaved posts
    $paginatedData = new \Illuminate\Pagination\LengthAwarePaginator(
        $interleavedPosts, // Interleaved posts
        $posts->total(), // Total posts count
        $posts->perPage(), // Items per page
        $posts->currentPage(), // Current page
        ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()] // Pagination links
    );

    // Return the query builder to allow further chaining
    return $builder; 
}


    

    public static function getWithComments(array $data)
    {
        $builder = self::list($data);
        $user_id = $data["user_id"] ?? auth()->id();
        $include_anonymuos = $data["include_anonymous"] ?? "0";

        $builder->whereHas("comments", function ($query) use ($user_id, $include_anonymuos) {
            $query->where("user_id", $user_id)
                ->where("is_anonymous", $include_anonymuos);
        });

        return $builder;
    }

    public static function getWithLikes(array $data)
    {
        $user_id = $data["user_id"] ?? auth()->id();

        unset($data["user_id"]);
        $builder = self::list($data);

        $builder->whereHas("reactions", function ($query) use ($user_id) {
            $field = is_numeric($user_id) ? "id" : "username";
            $query->whereRelation("user", $field, $user_id)
                ->where([
                    "action" => PostConstants::LIKE
                ]);
        });

        return $builder;
    }

    public function getAllMedia(array $data = [])
    {
        try {
            $validator = Validator::make($data, [
                "user_id" => "required|" . Rule::requiredIf(empty($id)),
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $data = $validator->validated();

            $posts = $this->getPostAttachments($data);
            $comments = $this->getCommentAttachments($data);

            $postsCollection = collect($posts);
            $commentsCollection = collect($comments);

            $merged_media = $postsCollection->merge($commentsCollection);

            $sortedMedia = $merged_media->sortByDesc('created_at');

            $page = $data['page'] ?? 1;
            $perPage = $data['per_page'] ?? AppConstants::API_PAGINATION_SIZE;

            $paginated = $this->paginateCollection($sortedMedia, $perPage, $page);

            $transformed = $paginated->map(function ($item) {
                return new MergeMedia($item);
            });

            return new LengthAwarePaginator(
                $transformed,
                $sortedMedia->count(),
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => request()->query()]
            );
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    protected function paginateCollection($collection, $perPage, $page)
    {
        $offset = ($page * $perPage) - $perPage;
        return $collection->slice($offset, $perPage)->values();
    }


    public function getPostAttachments($data)
    {
        $field = is_numeric($data["user_id"]) ? "id" : "username";
        return PostAttachment::whereRelation("user", $field, $data["user_id"])->whereHas("post", function ($post) use ($data) {
            $post->status();
            if (!empty($data["exclude_anonymous"] ?? null)) {
                $post->where("is_anonymous", 0);
            }
        })->latest()->get()
            ->map(function ($attachment) {
                return [
                    "id" => $attachment->id,
                    "url" => $attachment->url,
                    "type" => $attachment->type,
                    "status" => $attachment->status,
                    "created_at" => $attachment->created_at,
                    "updated_at" => $attachment->created_at,
                ];
            });
    }

    public function getCommentAttachments($data)
    {
        $field = is_numeric($data["user_id"]) ? "id" : "username";
        $builder = PostComment::whereRelation("user", $field, $data["user_id"])->whereNotNull("attachment");

        if (!empty($data["exclude_anonymous"] ?? null)) {
            $builder = $builder->where("is_anonymous", 0);
        }

        return $builder->latest()->get()
            ->map(function ($comment) {
                return [
                    "id" => $comment->id,
                    "url" => $comment->attachment,
                    "type" => $comment->type ?? "Image",
                    "status" => $comment->status,
                    "created_at" => $comment->created_at,
                    "updated_at" => $comment->created_at,
                ];
            });
    }
}
