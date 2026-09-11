<?php

namespace App\Http\Controllers\Api\V2\Therapist;

use App\Constants\General\ApiConstants;
use App\Constants\General\StatusConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\Therapist\TherapistDirectoryService;
use App\Services\User\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Exception;

class TherapistProfileController extends Controller
{
    private function forbiddenUnlessTherapist()
    {
        if (empty(auth()->user()->therapist)) {
            return ApiHelper::problemResponse("Forbidden", ApiConstants::FORBIDDEN_ERR_CODE, null, null);
        }

        return null;
    }

    /**
     * Own public profile (§07 shape), self-view.
     */
    public function show()
    {
        if ($forbidden = $this->forbiddenUnlessTherapist()) {
            return $forbidden;
        }

        try {
            $therapist = auth()->user()->therapist
                ->loadAvg('reviews as rating_avg', 'rating')
                ->loadCount('reviews');

            return ApiHelper::validResponse(
                "Profile returned successfully",
                TherapistDirectoryService::profile($therapist)
            );
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    /**
     * Therapist edit profile: bio/experience/rate. credential_type is
     * read-only post-approval (re-verification path — decision).
     */
    public function update(Request $request)
    {
        if ($forbidden = $this->forbiddenUnlessTherapist()) {
            return $forbidden;
        }

        try {
            $min = config('therapist.session_rate.min');
            $max = config('therapist.session_rate.max');

            $validator = Validator::make($request->all(), [
                "name" => "nullable|string|max:150",
                "avatar" => "nullable|string",
                "bio" => "nullable|string|max:300",
                "years_experience" => "nullable|integer|min:0|max:80",
                "session_rate" => "nullable|numeric|min:$min|max:$max",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $validated = collect($validator->validated())->filter(fn ($v) => $v !== null)->all();

            $user = auth()->user();
            $therapist = $user->therapist;

            $user_fields = array_intersect_key($validated, array_flip(["avatar", "bio"]));
            if (isset($validated["name"])) {
                $user_fields = array_merge($user_fields, UserService::getNames($validated["name"]));
            }
            if (!empty($user_fields)) {
                $user->update($user_fields);
            }

            // credential_type is deliberately never read from the payload.
            $therapist_fields = array_intersect_key($validated, array_flip(["years_experience", "session_rate"]));
            if (!empty($therapist_fields)) {
                $therapist->update($therapist_fields);
            }

            return ApiHelper::validResponse("Profile updated successfully", [
                "bio" => $user->refresh()->bio,
                "years_experience" => $therapist->refresh()->years_experience,
                "session_rate" => $therapist->session_rate,
                "credential_type" => $therapist->credential_type,
            ]);
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    /**
     * Pulls the therapist out of client search immediately (§ directory
     * reads Therapist::status()) without touching existing bookings —
     * reversible via reactivate(). No admin/verification impact.
     */
    public function deactivate()
    {
        if ($forbidden = $this->forbiddenUnlessTherapist()) {
            return $forbidden;
        }

        try {
            $therapist = auth()->user()->therapist;
            $therapist->update(["status" => StatusConstants::INACTIVE]);

            return ApiHelper::validResponse("Profile deactivated", ["status" => $therapist->status]);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function reactivate()
    {
        if ($forbidden = $this->forbiddenUnlessTherapist()) {
            return $forbidden;
        }

        try {
            $therapist = auth()->user()->therapist;
            $therapist->update(["status" => StatusConstants::ACTIVE]);

            return ApiHelper::validResponse("Profile reactivated", ["status" => $therapist->status]);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
