<?php

namespace App\Http\Controllers\Api\V1\User\Post;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Post\PostCommentResource;
use App\Http\Resources\Post\PostResource;
use App\Services\Post\PostCommentService;
use App\Services\Post\PostService;
use App\Services\Post\PostStatsService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PostCommentController extends Controller
{
    protected $post_service;
    protected $post_stats_service;
    protected $post_comment_service;

    public function __construct()
    {
        $this->post_service = new PostService;
        $this->post_comment_service = new PostCommentService;
        $this->post_stats_service = new PostStatsService;
    }

    public function index(Request $request)
    {
        try {
            $comments = $this->post_comment_service->list($request->all())->unblocked()->latest("id")->get();
            // Interleave promoted posts into comments
            // $interleavedComments = $this->interleavePromotedPostsIntoComments($comments);
            $data = PostCommentResource::collection($comments);
            return ApiHelper::validResponse("Post comments returned successfully", $data);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function show($id)
    {
        try {
            $comment = $this->post_comment_service->getById($id);
            $this->post_stats_service->dispatch(["post_id" => $comment->post_id, "comments" => true, "engagements" => true]);
            $data = PostCommentResource::make($comment);
            return ApiHelper::validResponse("Post comment returned successfully", $data);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function store(Request $request)
    {
        try {
            $comment = $this->post_comment_service->create($request->all());
            $data = PostCommentResource::make($comment);
            return ApiHelper::validResponse("Post comment created successfully", $data);
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $th);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $comment = $this->post_comment_service->update($request->all(), $id);
            $data = PostResource::make($comment);
            return ApiHelper::validResponse("Post comment updated successfully", $data);
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $th);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $this->post_comment_service->delete($id);
            return ApiHelper::validResponse("Post comment deleted successfully");
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function reaction(Request $request)
    {
        try {
            $response = $this->post_comment_service->handleReaction($request->all());
            return ApiHelper::validResponse("Comment reaction submitted successfully", $response);
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $th);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $th) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $th);
        }
    }
}
