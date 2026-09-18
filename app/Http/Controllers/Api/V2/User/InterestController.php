<?php

namespace App\Http\Controllers\Api\V2\User;

use App\Constants\General\ApiConstants;
use App\Constants\Post\PostCategoryConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\PostCategory\PostCategoryResource;
use App\Models\PostCategory;
use App\Services\User\InterestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Exception;

class InterestController extends Controller
{
    public $interest_service;
    function __construct()
    {
        $this->interest_service = new InterestService;
    }

    /**
     * Interest chips for onboarding — selected by the type marker, never by ID.
     */
    public function topics()
    {
        try {
            $topics = PostCategory::where("type", PostCategoryConstants::TYPE_INTEREST_TOPIC)
                ->orderBy("name")
                ->get();

            return ApiHelper::validResponse(
                "Interest topics returned successfully",
                PostCategoryResource::collection($topics)
            );
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    /**
     * Batch interest selection from the onboarding screen — minimum of 3.
     */
    public function sync(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                "interests" => "required|array|min:3",
                "interests.*" => "required|exists:post_categories,id",
            ], [
                "interests.min" => "Select at least 3 interests",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $user = auth()->user();
            foreach ($validator->validated()["interests"] as $category_id) {
                $this->interest_service->setUser($user)->save([
                    "category_id" => $category_id,
                ]);
            }

            DB::commit();

            $interests = $user->interests()->pluck("category_id");
            return ApiHelper::validResponse("Interests saved successfully", [
                "interests" => $interests,
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            DB::rollBack();
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
