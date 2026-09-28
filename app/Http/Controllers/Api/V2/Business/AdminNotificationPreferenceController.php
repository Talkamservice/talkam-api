<?php

namespace App\Http\Controllers\Api\V2\Business;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * The admin Settings screen's Notification Preferences card (web §03). Personal
 * to the signed-in admin's own account — mirrors the consumer
 * NotificationPreferenceController pattern on the same shared table, just a
 * different key set. Two admins on the same company can set these differently.
 */
class AdminNotificationPreferenceController extends Controller
{
    const KEYS = [
        'digest_summary',
        'seat_limit_alerts',
        'invoice_notifications',
        'new_therapist_announcements',
    ];

    /**
     * Applied only when a row has never set the key (null) — the DB column
     * default is not relied on, matching the consumer
     * NotificationPreferenceController's approach on this same table.
     */
    const DEFAULTS = [
        'digest_summary' => true,
        'seat_limit_alerts' => true,
        'invoice_notifications' => true,
        'new_therapist_announcements' => false,
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
                ->mapWithKeys(fn ($key) => [
                    $key => is_null($row->$key) ? self::DEFAULTS[$key] : (bool) $row->$key,
                ])
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
