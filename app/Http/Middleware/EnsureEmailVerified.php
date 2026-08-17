<?php

namespace App\Http\Middleware;

use App\Constants\General\ApiConstants;
use App\Helpers\ApiHelper;
use Closure;
use Illuminate\Http\Request;

/**
 * Blocks progress on flows that require a confirmed email address. First use:
 * the therapist application wizard — register-therapist already sends a
 * verify_email OTP (VerifyService::sendPin), this just stops the write steps
 * (personal/documents/specialties/availability/payout/submit) from being
 * reachable until that code is confirmed via POST /auth/otp/verify. Reading
 * application state stays open so the frontend can show the prompt.
 */
class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next)
    {
        if (empty($request->user()?->email_verified_at)) {
            return ApiHelper::problemResponse(
                "Please verify your email before continuing.",
                ApiConstants::FORBIDDEN_ERR_CODE,
                null,
                null
            );
        }

        return $next($request);
    }
}
