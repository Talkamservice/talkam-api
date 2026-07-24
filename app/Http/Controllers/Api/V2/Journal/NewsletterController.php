<?php

namespace App\Http\Controllers\Api\V2\Journal;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\Journal\JournalService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * The Journal's newsletter capture (web §05). Public and throttled; idempotent
 * on email so a repeat subscribe is a plain success.
 */
class NewsletterController extends Controller
{
    public function subscribe(Request $request)
    {
        try {
            JournalService::subscribe($request->only(["email", "source"]));

            return ApiHelper::validResponse("You're subscribed. Watch your inbox.", []);
        } catch (ValidationException $e) {
            return ApiHelper::problemResponse(
                "Enter a valid email address.",
                ApiConstants::VALIDATION_ERR_CODE,
                $request,
                $e
            );
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, $request, $e);
        }
    }
}
