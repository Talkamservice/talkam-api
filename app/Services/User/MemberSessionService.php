<?php

namespace App\Services\User;

use App\Constants\Business\OrganizationConstants;
use App\Constants\Therapist\TherapistConstants;
use App\Models\TherapySession;
use App\Models\User;
use App\Services\Business\SessionCapService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Member-side session extras the web dashboard needs on top of §07/§08:
 * the summary strip and the pre/post session mood pair.
 */
class MemberSessionService
{
    /**
     * The four tiles above the sessions list: upcoming, completed,
     * sessions used this quarter, and the allowance they draw against.
     */
    public static function summary(User $user): array
    {
        $upcoming = TherapySession::where('user_id', $user->id)
            ->where('starts_at', '>', now())
            ->where('status', TherapistConstants::SESSION_CONFIRMED)
            ->count();

        $completed = TherapySession::where('user_id', $user->id)
            ->where('status', TherapistConstants::SESSION_COMPLETED)
            ->count();

        $quarter_start = now()->firstOfQuarter();

        $used_this_quarter = TherapySession::where('user_id', $user->id)
            ->where('status', TherapistConstants::SESSION_COMPLETED)
            ->where('starts_at', '>=', $quarter_start)
            ->count();

        $cap_status = SessionCapService::status($user);

        return [
            'upcoming' => $upcoming,
            'completed' => $completed,
            'sessions_used' => $used_this_quarter,
            'sessions_allowed' => self::allowanceFor($user),
            'quarter_start' => $quarter_start->toDateString(),
            // The admin-set per-employee cap (web §03 Session Policy) — distinct
            // from the company-wide bundle above: null cap means uncapped.
            'employee_cap' => $cap_status['cap'] ?? null,
            'employee_cap_used' => $cap_status['used'] ?? 0,
        ];
    }

    /**
     * The company's pre-purchased session bundle, or null for a member with no
     * organization (a direct consumer has no cap to render).
     *
     * §01 stores the bundle at COMPANY level — this is deliberately not a
     * per-employee quota. A per-member cap would be a new product rule, not a
     * reinterpretation of this number.
     */
    private static function allowanceFor(User $user): ?int
    {
        $membership = $user->organizationMemberships()
            ->where('status', OrganizationConstants::MEMBER_ACTIVE)
            ->with('organization')
            ->first();

        $bundle = $membership?->organization?->session_bundle_sessions;

        return $bundle ? (int) $bundle : null;
    }

    /**
     * Pre-session and post-session mood, captured by the "before you join" and
     * feedback modals. Stored on the session, owned by the client.
     */
    public function saveMood(TherapySession $session, array $data): TherapySession
    {
        $validator = Validator::make($data, [
            'phase' => ['required', 'string', Rule::in(['pre', 'post'])],
            'mood' => 'required|integer|between:'
                . \App\Constants\Account\User\MoodConstants::MIN . ','
                . \App\Constants\Account\User\MoodConstants::MAX,
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $column = $validated['phase'] === 'pre' ? 'client_pre_mood' : 'client_post_mood';

        $session->update([$column => $validated['mood']]);

        return $session->refresh();
    }
}
