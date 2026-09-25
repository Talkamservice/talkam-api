<?php

namespace App\Services\PlatformAdmin;

use App\Constants\Account\User\ConsentConstants;
use App\Constants\Business\OrganizationConstants;
use App\Constants\Therapist\TherapistConstants;
use App\Models\MoodCheckin;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\TherapistApplication;
use App\Models\TherapySession;
use App\Models\User;
use App\QueryBuilders\User\UserQueryBuilder;
use App\Services\User\ConsentService;
use App\Services\User\MoodCheckinService;
use App\Services\User\PrivacySettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Platform-wide "All Users" — UserQueryBuilder::filterList() already has the
 * search/status/date-range logic (from the Blade Admin\User\UserController),
 * but that controller hardcodes role=User. This drops that constraint (a
 * platform admin needs to find a therapist or business member too) and adds
 * a "type" facet the mockup's tabs need — derived from the real role +
 * organization_members data (there is no separate "user type" column).
 *
 * NOT the same as "Platform" (mobile/web/both) in the design mockup — that
 * column has no honest backing (users.registration_platform is only ever
 * set to an OAuth provider name, e.g. "google", on the ~0 rows that used
 * social login; it isn't a mobile/web/both signal), so it's left out
 * rather than faked.
 */
class PlatformUserService
{
    const TYPE_REGULAR = "regular";
    const TYPE_EMPLOYEE = "employees";
    const TYPE_THERAPIST = "therapists";
    const TYPE_BUSINESS = "business";

    public static function list(Request $request, int $per_page = 20)
    {
        $query = self::scopeByType(UserQueryBuilder::filterList($request), $request->input('type'));

        $users = $query
            ->with([
                'organizationMemberships' => fn ($q) => $q->where('status', OrganizationConstants::MEMBER_ACTIVE),
                'therapist:id,user_id',
            ])
            ->latest()
            ->paginate($per_page);

        $users->getCollection()->transform(fn (User $u) => [
            "id" => $u->id,
            "name" => trim("{$u->first_name} {$u->last_name}"),
            "email" => $u->email,
            "role" => $u->role,
            "type" => self::typeOf($u),
            "status" => $u->status,
            "strike" => $u->strike,
            "suspension_end" => $u->suspension_end,
            "created_at" => $u->created_at->toDateTimeString(),
        ]);

        return $users;
    }

    /** Sidebar tab counts — one cheap indexed count query per facet. */
    public static function typeCounts(): array
    {
        $membershipType = fn ($role) => User::whereHas(
            'organizationMemberships',
            fn ($q) => $q->where('status', OrganizationConstants::MEMBER_ACTIVE)->where('role', $role)
        )->count();

        $employees = $membershipType(OrganizationConstants::ROLE_EMPLOYEE);
        $business = $membershipType(OrganizationConstants::ROLE_ADMIN);
        // Not role=Therapist — that column only flips on admin approval and
        // several therapists() rows predate/bypass that flow (seeded or
        // business-invited), same gap TherapistProfileController works
        // around by checking the therapist() relation instead.
        $therapists = User::whereHas('therapist')->count();
        $regular = User::whereDoesntHave('therapist')
            ->whereDoesntHave('organizationMemberships', fn ($q) => $q->where('status', OrganizationConstants::MEMBER_ACTIVE))
            ->count();

        return [
            "all" => User::count(),
            self::TYPE_REGULAR => $regular,
            self::TYPE_EMPLOYEE => $employees,
            self::TYPE_THERAPIST => $therapists,
            self::TYPE_BUSINESS => $business,
        ];
    }

    private static function scopeByType($query, ?string $type)
    {
        return match ($type) {
            self::TYPE_THERAPIST => $query->whereHas('therapist'),
            self::TYPE_EMPLOYEE => $query->whereHas(
                'organizationMemberships',
                fn ($q) => $q->where('status', OrganizationConstants::MEMBER_ACTIVE)->where('role', OrganizationConstants::ROLE_EMPLOYEE)
            ),
            self::TYPE_BUSINESS => $query->whereHas(
                'organizationMemberships',
                fn ($q) => $q->where('status', OrganizationConstants::MEMBER_ACTIVE)->where('role', OrganizationConstants::ROLE_ADMIN)
            ),
            self::TYPE_REGULAR => $query->whereDoesntHave('therapist')
                ->whereDoesntHave('organizationMemberships', fn ($q) => $q->where('status', OrganizationConstants::MEMBER_ACTIVE)),
            default => $query,
        };
    }

    /** Which surfaces an account type can sign in to — a product fact
     *  derived from type, NOT a tracked "last used device" signal (nothing
     *  like that exists; users.registration_platform only holds an OAuth
     *  provider name on social-login rows). */
    const PLATFORM_BY_TYPE = [
        self::TYPE_REGULAR => "Mobile",
        self::TYPE_EMPLOYEE => "Both",
        self::TYPE_THERAPIST => "Both",
        self::TYPE_BUSINESS => "Web",
    ];

    const MOOD_WINDOW_DAYS = 30;

    /**
     * Everything the profile Overview tab renders, in one round-trip. All
     * figures are real: mood/streak/factors come straight from
     * MoodCheckinService, counts from their own tables, and the wellbeing
     * score is an explicitly-labelled derivation of the 30-day mood average
     * (there is no stored "mental health score" anywhere — see wellbeing()).
     */
    public static function detail(int $id): array
    {
        $user = User::with(['organizationMemberships' => fn ($q) => $q->where('status', OrganizationConstants::MEMBER_ACTIVE)->with('organization:id,name'), 'therapist:id,user_id'])
            ->findOrFail($id);

        $membership = $user->organizationMemberships->first();
        $type = self::typeOf($user);
        $consents = ConsentService::state($user);
        $two_factor = PrivacySettingService::twoFactorEnabled($user);
        $mood = MoodCheckinService::summary($user, self::MOOD_WINDOW_DAYS);

        $sessions_count = TherapySession::where('user_id', $user->id)->count();
        $checkins_count = MoodCheckin::where('user_id', $user->id)->count();

        // The design's five profile-completion items, each a real fact.
        $checklist = [
            ["key" => "avatar", "label" => "Profile photo", "done" => !empty($user->avatar)],
            ["key" => "interests", "label" => "Interests set", "done" => $user->interests()->count() >= 3],
            ["key" => "first_checkin", "label" => "First check-in", "done" => $checkins_count > 0],
            ["key" => "therapist_booked", "label" => "Therapist booked", "done" => $sessions_count > 0],
            ["key" => "two_factor", "label" => "2FA enabled", "done" => $two_factor],
        ];
        $completion = (int) round((count(array_filter($checklist, fn ($i) => $i["done"])) / count($checklist)) * 100);

        return [
            "id" => $user->id,
            "name" => trim("{$user->first_name} {$user->last_name}"),
            "email" => $user->email,
            "phone_number" => $user->phone_number,
            "role" => $user->role,
            "type" => $type,
            "platform" => self::PLATFORM_BY_TYPE[$type],
            "status" => $user->status,
            "strike" => $user->strike,
            "suspend_ban_reason" => $user->suspend_ban_reason,
            "suspension_end" => $user->suspension_end,
            "created_at" => $user->created_at->toDateTimeString(),
            "organization_name" => $membership?->organization?->name,
            "last_active_at" => self::lastActiveAt($user->id),
            "flags" => [
                "two_factor_enabled" => $two_factor,
                "email_verified" => !empty($user->email_verified_at),
                "research_consent" => (bool) ($consents[ConsentConstants::ANONYMISED_RESEARCH]['granted'] ?? false),
                // No phone-verification flow exists, so a phone on file is
                // genuinely unverified; absent phone is its own state.
                "phone" => empty($user->phone_number) ? "none" : "unverified",
            ],
            "profile_completion" => [
                "percent" => $completion,
                "label" => $completion >= 75 ? "Good" : ($completion >= 50 ? "Fair" : "Low"),
                "items" => $checklist,
            ],
            "counts" => [
                "sessions" => $sessions_count,
                "sessions_this_month" => TherapySession::where('user_id', $user->id)
                    ->whereMonth('starts_at', now()->month)->whereYear('starts_at', now()->year)->count(),
                "checkins" => $checkins_count,
                "posts" => $user->posts()->count(),
                "comments" => PostComment::where('user_id', $user->id)->count(),
            ],
            "streak" => $mood['streak'],
            "mood" => $mood,
            "wellbeing" => self::wellbeing($mood),
            "recent_sessions" => self::sessions($id, 3)->items(),
        ];
    }

    /**
     * Derived, not stored: the 30-day mood average (1–5) scaled to 0–100,
     * compared against the platform-wide 30-day average for the
     * above/below label, with the month-over-month mood delta as the
     * change note. Null when the user has no check-ins in the window — the
     * UI shows an empty state rather than a made-up number.
     */
    private static function wellbeing(array $mood): ?array
    {
        if ($mood['average_mood'] === null) {
            return null;
        }

        $platform_avg = MoodCheckin::where('checked_in_on', '>=', now()->subDays(self::MOOD_WINDOW_DAYS)->toDateString())->avg('mood');
        $diff = $platform_avg ? $mood['average_mood'] - $platform_avg : 0;

        return [
            "score" => (int) round(($mood['average_mood'] / 5) * 100),
            "comparison" => $diff > 0.25 ? "Above average" : ($diff < -0.25 ? "Below average" : "Around average"),
            "month_delta_percent" => $mood['month_delta_percent'],
            "basis" => "Derived from {$mood['days_logged']} mood check-ins over the last " . self::MOOD_WINDOW_DAYS . " days",
        ];
    }

    /** Most recent timestamp across every table a user acts in — the
     *  honest "last active" (no last_login_at column exists; the write in
     *  LoginService is a no-op against a missing column). */
    private static function lastActiveAt(int $id): ?string
    {
        $candidates = [
            TherapySession::where('user_id', $id)->max('client_joined_at'),
            MoodCheckin::where('user_id', $id)->max('created_at'),
            Post::where('user_id', $id)->max('created_at'),
            PostComment::where('user_id', $id)->max('created_at'),
            DB::table('messages')->where('sender_id', $id)->max('created_at'),
        ];

        $latest = collect($candidates)->filter()->map(fn ($t) => (string) $t)->max();

        return $latest ?: null;
    }

    /** Chronological milestones from real timestamps — the "Journey" tab. */
    public static function journey(int $id): array
    {
        $user = User::findOrFail($id);

        $firstCheckin = MoodCheckin::where('user_id', $id)->min('created_at');
        $firstSession = TherapySession::where('user_id', $id)->min('created_at');
        $firstCompleted = TherapySession::where('user_id', $id)->where('status', TherapistConstants::SESSION_COMPLETED)->min('ended_at');
        $firstPost = Post::where('user_id', $id)->min('created_at');
        $application = TherapistApplication::where('user_id', $id)->latest()->first();
        $membership = $user->organizationMember();

        $events = [
            ["key" => "joined", "label" => "Account created", "at" => $user->created_at],
            ["key" => "email_verified", "label" => "Email verified", "at" => $user->email_verified_at],
            ["key" => "onboarding", "label" => "Onboarding completed", "at" => $user->onboarding_completed_at],
            ["key" => "org_joined", "label" => $membership ? "Joined {$membership->organization?->name}" : null, "at" => $membership?->created_at],
            ["key" => "first_checkin", "label" => "First mood check-in", "at" => $firstCheckin],
            ["key" => "first_post", "label" => "First community post", "at" => $firstPost],
            ["key" => "first_booking", "label" => "First session booked", "at" => $firstSession],
            ["key" => "first_completed", "label" => "First session completed", "at" => $firstCompleted],
            ["key" => "application_submitted", "label" => "Therapist application submitted", "at" => $application?->submitted_at],
            ["key" => "application_reviewed", "label" => $application ? "Therapist application " . $application->status : null, "at" => $application?->reviewed_at],
        ];

        return collect($events)
            ->filter(fn ($e) => !empty($e["at"]) && !empty($e["label"]))
            ->map(fn ($e) => ["key" => $e["key"], "label" => $e["label"], "at" => (string) $e["at"]])
            ->sortBy("at")
            ->values()
            ->all();
    }

    public static function sessions(int $id, int $per_page = 10)
    {
        return TherapySession::where('user_id', $id)
            ->with(['therapist.user:id,first_name,last_name', 'review:id,session_id,rating'])
            ->latest('starts_at')
            ->paginate($per_page);
    }

    public static function community(int $id, int $limit = 10): array
    {
        return [
            "posts" => Post::where('user_id', $id)->latest()->limit($limit)->get(['id', 'title', 'body', 'status', 'created_at']),
            "comments" => PostComment::where('user_id', $id)->latest()->limit($limit)->get(['id', 'post_id', 'comment', 'created_at']),
        ];
    }

    /** Real streak/average/trend/series — MoodCheckinService already
     *  computes all of this; nothing new to build here. */
    public static function mood(int $id)
    {
        return MoodCheckinService::summary(User::findOrFail($id));
    }

    /** Public — reused by PlatformDisputeService to label a reporter's
     *  type the same way the Users page does. */
    public static function typeOf(User $u): string
    {
        if ($u->therapist) {
            return self::TYPE_THERAPIST;
        }

        $membership = $u->organizationMemberships->first();
        if ($membership?->role === OrganizationConstants::ROLE_ADMIN) {
            return self::TYPE_BUSINESS;
        }
        if ($membership?->role === OrganizationConstants::ROLE_EMPLOYEE) {
            return self::TYPE_EMPLOYEE;
        }

        return self::TYPE_REGULAR;
    }
}
