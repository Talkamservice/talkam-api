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
use Carbon\Carbon;
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
        if (!empty($key = $data["search"] ?? null)) {
            $builder = $builder->search($key);
        }

        if (!empty($key = $data["category_id"] ?? null)) {
            $builder = $builder->where("category_id", $key);
        }

        if (!empty($key = $data["group_id"] ?? null)) {
            $field = is_numeric($key) ? "id" : "uuid";
            $builder = $builder->whereRelation("group", $field, $key);
        }

        if (!empty($key = $data["type"] ?? null)) {
            if (in_array($key, [PostConstants::MEDIA])) {
                $builder = $builder->whereIn("type", [PostConstants::FILE, PostConstants::IMAGE, PostConstants::VIDEO]);
            } else {
                $builder = $builder->where("type", $key);
            }
        }

        if (!empty($key = $data["exclude_anonymous"] ?? null)) {
            $builder = $builder->where("is_anonymous", 0);
        }

        if (!empty($key = $data["target"] ?? null)) {
            if ($key == "group") {
                $builder = $builder->whereRelation("group", "group_access", StatusConstants::OPENED);
            }
        }

        if (!empty($key = $data["user_id"] ?? null)) {
            $field = is_numeric($key) ? "id" : "username";
            $builder = $builder->whereRelation("user", $field, $key);
        } else {
            $builder =  $builder->whereDoesntHave('promotions', function ($promo) {
                $promo->where("status", StatusConstants::ACTIVE);
            });
        }

        if (!empty($key = $data["tab"] ?? null)) {
            $tags = TrendingTag::orderByDesc("count")->limit(20)->pluck("tag")->toArray();

            if ($key == "latest") {
                $builder = $builder->latest();
            }

            if ($key == "featured") {
                $builder = $builder->whereBetween('created_at', [now()->subMonths(2), now()])
                    ->withCount('comments') // Counts the comments
                    ->withCount(['reactions as likes_count' => function ($query) {
                        $query->where('action', PostConstants::LIKE); // Counts likes in the user_post_reactions table
                    }])
                    ->orderByRaw('(comments_count + likes_count) DESC');
            }


            if ($key == "trending") {
                $builder = $builder->where(function ($query) use ($tags) {
                    if (!empty($tags)) {
                        $query = $query->whereNotNull("tags");
                        $query = $query->where('tags', 'like', "%{$tags[0]}%");
                        foreach ($tags as $tag) {
                            $query = $query->orWhere('tags', 'like', "%{$tag}%");
                        }
                    }

                    // $query->orWhereHas('promotions', function ($promotion_query) {
                    //     $promotion_query = $promotion_query->where("status", StatusConstants::ACTIVE);
                    //     if (auth("sanctum")->check()) {
                    //         $user = auth("sanctum")->user();
                    //         $promotion_query->whereIn("country_id", [$user->country_id])->inRandomOrder();
                    //     } else {
                    //         $promotion_query->inRandomOrder();
                    //     }
                    // });

                    // Include posts liked by the authenticated user
                    // if (auth("sanctum")->check()) {
                    //     $user = auth("sanctum")->user();
                    //     $query = $query->orWhereHas('reactions', function ($reactionQuery) use ($user) {
                    //         $reactionQuery->where('user_id', $user->id)
                    //             ->where('action', PostConstants::LIKE);
                    //     });
                    // }
                });

                $builder = $builder->latest();
            }
        }

        return  $builder;
    }

    public function getByPost()
    {
        $builder = Post::with('user')
            ->whereHas('promotions', function ($query) {
                $user = auth('sanctum')->user();
                $query->whereNull('group_id');

                if (!empty($country_id = $user?->country_id)) {
                    $query->whereRelation("promotionLocations", 'country_id', $country_id);
                }

                if (!empty($age = $user?->age)) {
                    $query->where(function ($q) use ($age) {
                        $q->where('min_age', '<=', $age)
                            ->where('max_age', '>=', $age);
                    });
                }

                // Filter by gender, considering special statuses
                if (!empty($gender = $user?->gender)) {
                    $query->where(function ($q) use ($gender) {
                        if (in_array($gender, [AppConstants::MALE, AppConstants::FEMALE])) {
                            $q->whereIn('gender', [$gender, 'All', null]);
                        } elseif ($gender == AppConstants::RATHER_NOT_SAY) {
                            $q->whereIn('gender', [AppConstants::RATHER_NOT_SAY, 'All', null]);
                        } elseif ($gender == AppConstants::OTHERS) {
                            $q->whereIn('gender', [AppConstants::OTHERS, 'All', null]);
                        }
                    });
                }

                $query->where(function ($q) {
                    $q->whereRaw('DATE_ADD(created_at, INTERVAL duration DAY) >= ?', [now()]);
                })->where('status', StatusConstants::ACTIVE);
            });


        if (!empty($key = $data["search"] ?? null)) {
            $builder = $builder->search($key);
        }

        if (!empty($key = $data["category_id"] ?? null)) {
            $builder = $builder->where("category_id", $key);
        }

        if (!empty($key = $data["group_id"] ?? null)) {
            $field = is_numeric($key) ? "id" : "uuid";
            $builder = $builder->whereRelation("group", $field, $key);
        }

        if (!empty($key = $data["type"] ?? null)) {
            if (in_array($key, [PostConstants::MEDIA])) {
                $builder = $builder->whereIn("type", [PostConstants::FILE, PostConstants::IMAGE, PostConstants::VIDEO]);
            } else {
                $builder = $builder->where("type", $key);
            }
        }
        return $builder;
    }

    // public static function interleavePromotedPosts($regularPosts, $target)
    // {
    //     // Fetch promoted posts
    //     $promotedPostsQuery = Promotion::where('status', StatusConstants::ACTIVE)
    //         ->with('post')
    //         ->get()
    //         ->filter(function ($promotion) {
    //             $expiresAt = Carbon::parse($promotion->created_at)->addDays($promotion->duration);
    //             return $expiresAt->greaterThanOrEqualTo(now());
    //         })
    //         ->sortByDesc(function ($promotion) {
    //             return $promotion->cost;
    //         });
    //     dd($promotedPostsQuery);
    //     // Filter promoted posts based on the target
    //     $promotedPosts = $promotedPostsQuery->filter(function ($promotion) use ($target) {
    //         if ($target === 'group') {
    //             // For group tab, include promotions with either group_id or post_id
    //             return $promotion->group_id || $promotion->post_id;
    //         } elseif ($target === 'post') {
    //             // For post tab, include promotions with post_id only
    //             return $promotion->post_id !== null;
    //         }

    //         // Default: include all promoted posts
    //         return true;
    //     })->pluck('post')->filter();

    //     // Interleave promoted posts with regular posts
    //     $interleavedPosts = [];
    //     $regularPostIndex = 0;
    //     $promotedPostIndex = 0;
    //     $regularPostInterval = 5;

    //     while ($regularPostIndex < $regularPosts->count()) {
    //         for ($i = 0; $i < $regularPostInterval && $regularPostIndex < $regularPosts->count(); $i++) {
    //             $post = $regularPosts->get($regularPostIndex);
    //             if ($post) {
    //                 $interleavedPosts[] = $post;
    //             }
    //             $regularPostIndex++;
    //         }

    //         $promotedPost = $promotedPosts->get($promotedPostIndex);
    //         if ($promotedPost) {
    //             $interleavedPosts[] = $promotedPost;
    //             $promotedPostIndex++;
    //         }
    //     }

    //     while ($promotedPostIndex < $promotedPosts->count()) {
    //         $promotedPost = $promotedPosts->get($promotedPostIndex);
    //         if ($promotedPost) {
    //             $interleavedPosts[] = $promotedPost;
    //         }
    //         $promotedPostIndex++;
    //     }

    //     return $interleavedPosts;
    // }


    public static function trends(array $data = [])
    {
        $builder = TrendingTag::query();
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