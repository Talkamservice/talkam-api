<?php

namespace App\Http\Controllers\Api\V2\Business;

use App\Constants\Business\OrganizationConstants;
use App\Constants\General\ApiConstants;
use App\Constants\Post\PostCategoryConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\PostCategory;
use App\Services\Business\SelfCheckService;
use App\Services\User\InterestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * Invited-member onboarding — topics of interest and the self check-in
 * (screens 8 and 9).
 *
 * Topics are a separate endpoint from the mobile v2 `user/profile/interests`
 * on purpose: mobile onboarding requires at least 3 chips (pinned by §02's
 * tests), while this screen enables Continue at 1. Both write through the same
 * InterestService, so there is still exactly one write path.
 */
class OnboardingController extends Controller
{
    public InterestService $interest_service;
    public SelfCheckService $self_check_service;

    public function __construct()
    {
        $this->interest_service = new InterestService;
        $this->self_check_service = new SelfCheckService;
    }

    /**
     * The six chips the web onboarding screen renders, in deck order, each
     * resolved to its shared interest-topic category id. Any chip whose
     * category has not been seeded yet is omitted rather than sent with a null
     * id — the screen never offers an unselectable option.
     */
    public function topicOptions()
    {
        try {
            $configured = config("business.onboarding_topics");

            $categories = PostCategory::where("type", PostCategoryConstants::TYPE_INTEREST_TOPIC)
                ->whereIn("name", array_column($configured, "category"))
                ->pluck("id", "name");

            $topics = [];

            foreach ($configured as $topic) {
                $id = $categories[$topic["category"]] ?? null;

                if (empty($id)) {
                    continue;
                }

                $topics[] = [
                    "id" => $id,
                    "key" => $topic["key"],
                    "label" => $topic["label"],
                ];
            }

            return ApiHelper::validResponse("Topics returned successfully", ["topics" => $topics]);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function topics(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                "interests" => "required|array|min:1",
                "interests.*" => "required|integer|exists:post_categories,id",
            ], [
                "interests.min" => "Pick at least one topic",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $ids = $validator->validated()["interests"];

            // Only interest-topic categories are selectable — the same marker
            // rule the mobile chips list uses, enforced server-side so a
            // crafted id cannot attach an arbitrary category.
            $valid = PostCategory::whereIn("id", $ids)
                ->where("type", PostCategoryConstants::TYPE_INTEREST_TOPIC)
                ->pluck("id");

            if ($valid->count() !== count($ids)) {
                throw new InvalidRequestException("One or more topics are not selectable.");
            }

            $user = auth()->user();

            foreach ($valid as $category_id) {
                $this->interest_service->setUser($user)->save([
                    "category_id" => $category_id,
                ]);
            }

            DB::commit();

            return ApiHelper::validResponse("Topics saved successfully", [
                "interests" => $user->interests()->pluck("category_id"),
            ]);
        } catch (ValidationException $e) {
            DB::rollBack();
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (InvalidRequestException $e) {
            DB::rollBack();
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::BAD_REQ_ERR_CODE, null, null);
        } catch (Exception $e) {
            DB::rollBack();
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function selfCheck(Request $request)
    {
        try {
            $membership = $request->attributes->get("organization_member");

            $data = $this->self_check_service->save($membership, $request->all());

            return ApiHelper::validResponse("Self check-in saved successfully", $data);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function selfCheckState(Request $request)
    {
        try {
            $membership = $request->attributes->get("organization_member");

            return ApiHelper::validResponse(
                "Self check-in returned successfully",
                array_merge(SelfCheckService::state($membership), [
                    "questions" => config("business.self_check.questions"),
                    "options" => config("business.self_check.options"),
                    "concerns" => OrganizationConstants::SELF_CHECK_CONCERNS,
                ])
            );
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
