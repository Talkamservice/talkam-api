<?php

namespace App\Services\Post;

use App\Constants\General\StatusConstants;
use App\Constants\Post\PostConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\PostComment;
use App\Models\Promotion;
use App\Models\UserCommentReaction;
use App\Services\Notification\NotificationHandlerService;
use App\Services\User\UserService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PostCommentService
{
    public static function getById($id): PostComment
    {
        $post_attachment = PostComment::find($id);
        if (empty($post_attachment)) {
            throw new ModelNotFoundException("Post comment not found");
        }
        return $post_attachment;
    }

    public static function validate($data, $id = null)
    {
        $validator = Validator::make($data, [
            "comment" => "nullable|string",
            "attachment" => "nullable|string",
            "post_id" => "required|exists:posts,id",
            "parent_id" => "nullable|exists:post_comments,id",
            "reply_comment_id" => "nullable|exists:post_comments,id",
            "is_anonymous" => "nullable|in:0,1|" . Rule::in(array_keys(StatusConstants::BOOL_OPTIONS)),
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public static function create(array $data)
    {
        $data = self::validate($data);
        $data["user_id"] = auth()->id();
        $comment = PostComment::create($data);

        $notification = (new NotificationHandlerService)->init($comment->post->user_id)
            ->notifyPostOwnerOfNewComment($comment)
            ->notifyThreadUser($comment, "comment");

        if (!empty($data["reply_comment_id"] ?? null)) {
            $notification->notifyCommentOwnerOfNewComment($comment);
        }

        $tag = findSpecialWords($comment->comment);
        $user = (new UserService)->getByUsername($tag);

        if (!empty($tag && $user)) {
            (new NotificationHandlerService)->init($user->id)
                ->notifyMentionOfNewComment($comment);
        }

        if ($comment->is_anonymous == 1) {
            $comment->user->increment("anonymous_comment");
        }
        return $comment;
    }

    public static function update(array $data, $id)
    {
        $data = self::validate($data, $id);
        $post = self::getById($id);
        $post->update($data);
        return $post->refresh();
    }

    public static function delete($post_comment_id)
    {
        $post = self::getById($post_comment_id);
        $post->delete();
    }

    public static function list(array $data = [])
    {
        $builder = PostComment::latest();

        if (!empty($key = $data["post_id"] ?? null)) {
            $builder = $builder->where("post_id", $key);
        }

        if (!empty($key = $data["comment_id"] ?? null)) {
            $builder = $builder->where("parent_id", $key);
        }

        if (!empty($key = $data["user_id"] ?? null)) {
            $field = is_numeric($key) ? "id" : "username";
            $builder = $builder->whereRelation("user", $field, $key);
        }

        if (!empty($key = $data["exclude_anonymous"] ?? null)) {
            $builder = $builder->where("is_anonymous", 0);
        }

        if (($data["type"] ?? null) != "all") {
            $builder = $builder->where("parent_id");
        }

        return $builder;
    }

    public function interleavePromotedPostsIntoComments($regularComments)
    {
        // Fetch promoted posts for interleaving
        $promotedPosts = Promotion::whereNotNull('post_id')
            ->whereNull('group_id')
            ->where('status', StatusConstants::ACTIVE)
            ->with('post') // Load related post
            ->get()
            ->filter(function ($promotion) {
                // Filter active promoted posts
                $expiresAt = Carbon::parse($promotion->created_at)->addDays($promotion->duration);
                return $expiresAt->greaterThanOrEqualTo(now());
            })
            ->sortByDesc(function ($promotion) {
                return $promotion->post->cost; // Sort by cost of the promoted post
            })
            ->pluck('post'); // Extract only the posts
    
        $interleavedComments = [];
        $regularCommentIndex = 0;
        $promotedPostIndex = 0;
        $regularCommentInterval = 10; // Number of regular comments between promoted posts
    
        // Interleave regular comments and promoted posts
        while ($regularCommentIndex < $regularComments->count()) {
            // Add up to 10 regular comments
            for ($i = 0; $i < $regularCommentInterval && $regularCommentIndex < $regularComments->count(); $i++) {
                $interleavedComments[] = $regularComments->get($regularCommentIndex); // Add regular comment
                $regularCommentIndex++;
            }
    
            // Add one promoted post if available
            if ($promotedPostIndex < $promotedPosts->count()) {
                $interleavedComments[] = $promotedPosts->get($promotedPostIndex); // Add promoted post directly
                $promotedPostIndex++;
            }
        }
    
        // Append any remaining promoted posts (if necessary)
        while ($promotedPostIndex < $promotedPosts->count()) {
            $interleavedComments[] = $promotedPosts->get($promotedPostIndex); // Add any remaining promoted posts
            $promotedPostIndex++;
        }
    
        return $interleavedComments;
    }
    


    public static function handleReaction(array $data)
    {
        $validator = Validator::make($data, [
            "comment_id" => "required|numeric|exists:post_comments,id",
            "action" => "required|string|" . Rule::in(PostConstants::REACTIONS),
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();

        $user = auth()->user();
        $comment_id = $data["comment_id"];
        $action = $data["action"];

        if (self::isReactionPresent($comment_id, $user->id, $action)) {
            self::removeReaction($comment_id, $user->id, $action);
        } else {
            self::addReaction($comment_id, $user->id, $action);
        }

        return [
            "action" => $action,
            "status" => self::isReactionPresent($comment_id, $user->id, $action),
        ];
    }

    public static function isReactionPresent($comment_id, $user_id, $action = PostConstants::LIKE)
    {
        return UserCommentReaction::where([
            "comment_id" => $comment_id,
            "user_id" => $user_id,
            "action" => $action,
        ])->exists();
    }

    public static function removeReaction($comment_id, $user_id, $action = PostConstants::LIKE)
    {
        UserCommentReaction::where([
            "comment_id" => $comment_id,
            "user_id" => $user_id,
            "action" => $action,
        ])->delete();
    }

    public static function addReaction($comment_id, $user_id, $action = PostConstants::LIKE)
    {
        $inverse = ($action == PostConstants::DISLIKE) ? PostConstants::LIKE : PostConstants::DISLIKE;

        $comment_reaction = UserCommentReaction::create([
            "comment_id" => $comment_id,
            "user_id" => $user_id,
            "action" => $action,
        ]);

        //Remove Inverse
        UserCommentReaction::where([
            "comment_id" => $comment_id,
            "user_id" => $user_id,
            "action" => $inverse,
        ])->delete();

        (new NotificationHandlerService)->init($comment_reaction->comment->user_id)
            ->notifyCommentOwnerOfNewReaction($comment_reaction)
            ->notifyThreadUser($comment_reaction, "comment_reaction");
    }
}
