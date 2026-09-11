<?php

namespace App\Http\Controllers\Api\V2\Post;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Post\PostCommentResource;
use App\Services\Post\PostCommentService;
use App\Services\User\MuteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Exception;

class PostCommentController extends Controller
{
    public $post_comment_service;
    function __construct()
    {
        $this->post_comment_service = new PostCommentService;
    }

    /**
     * v1 comment listing plus the v2 mute exclusion (feeds-only).
     */
    public function index(Request $request)
    {
        try {
            $builder = $this->post_comment_service->list($request->all())->unblocked();

            $muted_ids = MuteService::mutedIds(auth()->user());
            if (!empty($muted_ids)) {
                $builder = $builder->whereNotIn("user_id", $muted_ids);
            }

            $comments = $builder->latest("id")->get();
            $data = PostCommentResource::collection($comments);
            return ApiHelper::validResponse("Post comments returned successfully", $data);
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    /**
     * All comments by a specific user (id or username) — same query `index`
     * already runs for `?user_id=`, just addressed by path.
     */
    public function byUser(Request $request, $id)
    {
        $request->merge(["user_id" => $id]);
        return $this->index($request);
    }

    /**
     * v2 reply: hard 500-char server-side max. Threading, mentions and the
     * per-reply anonymous flag reuse the shared v1 service.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "comment" => "nullable|string|max:500",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

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
}
