<?php

namespace App\Http\Controllers\Api\V1\User\Post;

use App\Constants\General\ApiConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Group\GroupResource;
use App\Http\Resources\Post\PostResource;
use App\Http\Resources\Post\TrendingResource;
use App\Http\Resources\PostCategory\PostCategoryResource;
use App\Services\Post\RecentViewService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RecentViewController extends Controller
{
    protected $recent_view_service;

    public function __construct()
    {
        $this->recent_view_service = new RecentViewService;
    }

    public function index(Request $request)
    {
        try {
            $data = $this->recent_view_service->list(auth()->id(), $request->all());
            $records = $data["records"]->get();

            $data = match ($data["key"]) {
                "post" => PostResource::collection($records),
                "tag" => TrendingResource::collection($records),
                "group" => GroupResource::collection($records),
                "category" => PostCategoryResource::collection($records),
            };

            return ApiHelper::validResponse("Recent returned successfully", $data);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, $request, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $this->recent_view_service->delete($id);
            return ApiHelper::validResponse("Item deleted successfully");
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (InvalidRequestException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
