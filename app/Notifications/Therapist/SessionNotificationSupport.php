<?php

namespace App\Notifications\Therapist;

use App\Constants\Therapist\TherapistConstants;
use Carbon\Carbon;

/**
 * Display helpers shared by the session-lifecycle notifications that render
 * the TalkAM Design templates. Kept separate from MethodsHelper's date
 * formatters — those have other callers with their own expected format and
 * shouldn't shift to match one email design's "Wed, Jul 16 · 2:00 PM" style.
 */
class SessionNotificationSupport
{
    private const FORMAT_LABELS = [
        TherapistConstants::FORMAT_VIDEO => 'Video call',
        TherapistConstants::FORMAT_VOICE => 'Voice call',
    ];

    public static function formatLabel(?string $format): ?string
    {
        return self::FORMAT_LABELS[$format] ?? $format;
    }

    public static function formatDateTime(Carbon $when): string
    {
        return $when->format('D, M j \· g:i A');
    }

    public static function formatTime(Carbon $when): string
    {
        return $when->format('g:i A');
    }

    /** No session-specific deep link exists anywhere else in the codebase
     *  today (every prior notification used the bare web_url) — these follow
     *  the same web_url + "/{entity}/{id}" convention used for posts/groups. */
    public static function sessionUrl($session): string
    {
        return config('app.web_url') . "/session/{$session->id}";
    }

    public static function rebookUrl($session): string
    {
        return config('app.web_url') . "/session/{$session->id}/rebook";
    }

    public static function rateUrl($session): string
    {
        return config('app.web_url') . "/session/{$session->id}/rate";
    }
}
