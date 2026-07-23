<?php

namespace App\Http\Controllers\Api\V2\Therapist;

use App\Constants\General\ApiConstants;
use App\Constants\Therapist\TherapistConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\TherapySession;
use App\Models\UserInterest;
use App\Notifications\Therapist\SessionAcknowledgedNotification;
use Illuminate\Support\Facades\Notification;
use Exception;

/**
 * Therapist lane (§10, §3a reconciliation): the request sheet + Accept.
 * Accept = acknowledgement (no state change); Decline = the §08 cancel.
 */
class SessionRequestController extends Controller
{
    /**
     * The session, only for its assigned therapist.
     */
    private function sessionForTherapist($session_id): TherapySession
    {
        $user = auth()->user();
        $session = TherapySession::with(['user', 'therapist'])->find($session_id);

        if (empty($session) || $session->therapist?->user_id != $user->id) {
            throw new ModelNotFoundException("Session not found");
        }

        return $session;
    }

    /**
     * Plain (non-therapist) users get 403 before any lookup.
     */
    private function forbiddenUnlessTherapist()
    {
        if (empty(auth()->user()->therapist)) {
            return ApiHelper::problemResponse("Forbidden", ApiConstants::FORBIDDEN_ERR_CODE, null, null);
        }

        return null;
    }

    /**
     * Therapist my-sessions (§12): mirrored upcoming/past with net earnings
     * and client ratings.
     */
    public function index()
    {
        if ($forbidden = $this->forbiddenUnlessTherapist()) {
            return $forbidden;
        }

        try {
            $data = \App\Services\Therapist\SessionBookingService::listForTherapist(auth()->user()->therapist);
            return ApiHelper::validResponse("Sessions returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function request($session_id)
    {
        if ($forbidden = $this->forbiddenUnlessTherapist()) {
            return $forbidden;
        }

        try {
            $session = $this->sessionForTherapist($session_id);
            $client = $session->user;

            $topics = UserInterest::with('category')
                ->where('user_id', $client->id)
                ->get()
                ->map(fn ($row) => $row->category?->name)
                ->filter()
                ->values()
                ->all();

            $prior = TherapySession::where('user_id', $client->id)
                ->where('therapist_id', $session->therapist_id)
                ->where('id', '!=', $session->id)
                ->whereIn('status', [
                    TherapistConstants::SESSION_CONFIRMED,
                    TherapistConstants::SESSION_COMPLETED,
                ])
                ->exists();

            $share = (float) config('therapist.platform_share_percent');
            $net = round((float) $session->amount * (1 - $share / 100), 2);

            return ApiHelper::validResponse("Session request returned successfully", [
                "id" => $session->id,
                "client_name" => $client?->full_name,
                "new_client" => !$prior,
                "topics" => $topics,
                "starts_at" => $session->starts_at->toDateTimeString(),
                "duration_minutes" => $session->duration_minutes,
                "format" => $session->format,
                "amount" => $session->amount,
                "you_receive" => $net,
                "currency" => $session->currency,
                "note" => $session->notes,
                "acknowledged_at" => $session->acknowledged_at?->toDateTimeString(),
            ]);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function acknowledge($session_id)
    {
        if ($forbidden = $this->forbiddenUnlessTherapist()) {
            return $forbidden;
        }

        try {
            $session = $this->sessionForTherapist($session_id);

            // First acknowledge wins; status never changes (§3a).
            if (empty($session->acknowledged_at)) {
                $session->update(['acknowledged_at' => now()]);
                Notification::send($session->user, new SessionAcknowledgedNotification($session));
            }

            return ApiHelper::validResponse("Session acknowledged successfully", [
                "acknowledged_at" => $session->refresh()->acknowledged_at?->toDateTimeString(),
                "status" => $session->status,
            ]);
        } catch (ModelNotFoundException $e) {
            return ApiHelper::problemResponse($e->getMessage(), ApiConstants::NOT_FOUND_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
