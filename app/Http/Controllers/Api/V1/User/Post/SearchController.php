<?php

namespace App\Http\Controllers\Api\V1\User\Post;

use App\Constants\General\ApiConstants;
use App\Constants\General\AppConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\Group\GroupResource;
use App\Http\Resources\Post\PostResource;
use App\Http\Resources\Users\TrendingSearchResource;
use App\Services\Post\SearchService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SearchController extends Controller
{
    protected $search_service;

    public function __construct()
    {
        $this->search_service = new SearchService;
    }

    public function index(Request $request)
    {
        try {
            $response = $this->search_service->search($request->all());
            $records = $response["records"]->paginate(AppConstants::API_PAGINATION_SIZE)->appends($request->query());

            $data = collectPagination($records);

            $data["data"] = match ($response["key"]) {
                "post" => PostResource::collection($data["data"]),
                "group" => GroupResource::collection($data["data"]),
                "media" => PostResource::collection($data["data"]),
            };

            return ApiHelper::validResponse("Search returned successfully", $data);
        } catch (ValidationException $th) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $th);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function trending(Request $request)
    {
        try {
            $trendings = $this->search_service->trending()->limit(10)->get()->toArray();
            return ApiHelper::validResponse("Trending search returned successfully", $trendings);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function recent(Request $request)
    {
        try {
            $trendings = $this->search_service->recent($request->all())->limit(10)->get();
            $data = TrendingSearchResource::collection($trendings);
            return ApiHelper::validResponse("Recent search returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function suggestions(Request $request)
    {
        try {
            $trendings = $this->search_service->suggestions($request->all())->limit(10)->get();
            $data = TrendingSearchResource::collection($trendings);
            return ApiHelper::validResponse("Recent search returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $this->search_service->delete($id);
            return ApiHelper::validResponse("Item deleted successfully");
        } catch (ModelNotFoundException $th) {
            return ApiHelper::problemResponse($th->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, $th);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
