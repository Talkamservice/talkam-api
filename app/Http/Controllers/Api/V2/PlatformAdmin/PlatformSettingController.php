<?php

namespace App\Http\Controllers\Api\V2\PlatformAdmin;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * Simple key/value platform settings — the design mockup's broader
 * settings surface has no backing data yet, so this deliberately only
 * exposes what's real: waitlist_mode, and the Performance Watch
 * thresholds (read by PlatformPerformanceService).
 */
class PlatformSettingController extends Controller
{
    private const KNOWN_KEYS = [
        'waitlist_mode',
        'performance_yellow_rating',
        'performance_red_rating',
        'performance_yellow_min_sessions',
        'performance_red_min_sessions',
        'performance_dispute_auto_suspend',
    ];

    public function index()
    {
        try {
            $settings = PlatformSetting::whereIn('key', self::KNOWN_KEYS)->get(['key', 'value', 'updated_at']);
            return ApiHelper::validResponse("Settings returned successfully", $settings);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }

    public function update(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "key" => ["required", Rule::in(self::KNOWN_KEYS)],
                "value" => "required",
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $validated = $validator->validated();
            PlatformSetting::set($validated['key'], is_bool($validated['value']) ? ($validated['value'] ? '1' : '0') : $validated['value']);

            return ApiHelper::validResponse("Setting updated", PlatformSetting::where('key', $validated['key'])->first());
        } catch (ValidationException $e) {
            return ApiHelper::inputErrorResponse($this->validationErrorMessage, ApiConstants::VALIDATION_ERR_CODE, null, $e);
        } catch (Exception $e) {
            return ApiHelper::problemResponse($this->serverErrorMessage, ApiConstants::SERVER_ERR_CODE, null, $e);
        }
    }
}
