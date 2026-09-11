<?php

namespace App\Http\Controllers\Api\V2\User;

use App\Constants\General\ApiConstants;
use App\Constants\General\StatusConstants;
use App\Constants\Therapist\TherapistConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\TherapySession;
use App\Services\Therapist\EarningsLedgerService;
use App\Services\User\UserService;
use Illuminate\Http\Request;
use Exception;

/**
 * v2 delete-account with the §14 therapist gate: no deletion while future
 * confirmed sessions or an unwithdrawn balance exist. Reviews are retained
 * (anonymised) via nullOnDelete FKs; the therapist row is marked Deleted so
 * the directory drops it while session/review history survives.
 */
class DeleteAccountController extends Controller
{
    public $user_service;
    function __construct()
    {
        $this->user_service = new UserService;
    }

    public function deleteAccount(Request $request)
    {
        try {
            $user = auth()->user();
            $therapist = $user->therapist;

            if (!empty($therapist)) {
                $upcoming = TherapySession::where('therapist_id', $therapist->id)
                    ->whereIn('status', [
                        TherapistConstants::SESSION_CONFIRMED,
                        TherapistConstants::SESSION_IN_PROGRESS,
                    ])
                    ->where('starts_at', '>', now())
                    ->exists();

                if ($upcoming) {
                    return ApiHelper::problemResponse(
                        "You still have upcoming sessions. Cancel them (clients are refunded automatically) before deleting your account.",
                        ApiConstants::BAD_REQ_ERR_CODE,
                        null,
                        null
                    );
                }

                if (EarningsLedgerService::balance($therapist) > 0) {
                    return ApiHelper::problemResponse(
                        "You still have an unwithdrawn balance. Withdraw your earnings before deleting your account.",
                        ApiConstants::BAD_REQ_ERR_CODE,
                        null,
                        null
                    );
                }

                // Drop from the directory; the row survives user deletion
                // (nullOnDelete) so reviews/sessions are retained.
                $therapist->update(['status' => StatusConstants::DELETED]);
            }

            try {
                $this->user_service->deleteAccount($request->all());
            } catch (Exception $e) {
                // v1's post-delete activity logging can throw AFTER the
                // deletion has committed — only re-throw if the user row
                // actually survived.
                if (\App\Models\User::find($user->id)) {
                    throw $e;
                }
                logger("Post-delete logging failed (account already deleted)", [
                    "error" => $e->getMessage(),
                ]);
            }

            return ApiHelper::validResponse("Account deleted successfully");
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
