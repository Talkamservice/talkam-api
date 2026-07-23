<?php

namespace App\Http\Controllers\Api\V2\User;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Exception;

class NotificationPreferenceController extends Controller
{
    /**
     * v2 superset keys. No SMS: the channel is not offered in v2 —
     * can_receive_sms is never read nor written on this lane.
     */
    const KEYS = [
        'session_confirmation',
        'session_reminders',
        'post_session_feedback',
        'payment_confirmations',
        'replies_to_posts',
        'promotions_updates',
        'wellness_nudges',
        'can_receive_push',
        'can_receive_mail',
    ];

    private function rowFor($user): NotificationPreference
    {
        return NotificationPreference::firstOrCreate(['user_id' => $user->id]);
    }

    public function index()
    {
        try {
            $row = $this->rowFor(auth()->user());

            $data = collect(self::KEYS)
                ->mapWithKeys(fn ($key) => [$key => (bool) ($row->$key ?? true)])
                ->all();

            return ApiHelper::validResponse("Notification preferences returned successfully", $data);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function store(Request $request)
    {
        try {
            $unknown = array_diff(array_keys($request->all()), self::KEYS);
            if (!empty($unknown)) {
                throw ValidationException::withMessages(
                    collect($unknown)->mapWithKeys(fn ($key) => [$key => ["Unknown preference key: $key"]])->all()
                );
            }

            $validator = Validator::make($request->all(), collect(self::KEYS)
                ->mapWithKeys(fn ($key) => [$key => 'nullable|boolean'])
                ->all());

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $row = $this->rowFor(auth()->user());
            $row->update(collect($validator->validated())
                ->filter(fn ($v) => $v !== null)
                ->map(fn ($v) => (int) $v)
                ->all());

            return $this->index();
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
