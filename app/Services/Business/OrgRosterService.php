<?php

namespace App\Services\Business;

use App\Constants\Business\OrganizationConstants;
use App\Constants\General\StatusConstants;
use App\Constants\Therapist\TherapistConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OrganizationTherapist;
use App\Models\Therapist;
use App\Models\TherapistCapacityRequest;
use App\Models\TherapistReview;
use App\Models\TherapistSessionRequest;
use App\Models\TherapySession;
use App\Models\User;
use App\Services\Therapist\TherapistSlotService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Seat administration for the admin dashboard.
 *
 * The roster() list is CONTRACT data (who holds a seat, in which department,
 * at what status) PLUS each employee seat's own session cap usage and
 * last-active timestamp — a deliberate, explicit design choice for this
 * dashboard's employee table, scoped to employee-role seats only (admin/
 * therapist seats and pending invitations carry no usage figures). It still
 * carries no session content, no mood/wellbeing signal and nothing about any
 * OTHER employee — company-wide behaviour stays exposed only in aggregate,
 * through OrgAggregateService. (planning-docs/web-api/03-admin-dashboard.md §0
 * predates this choice — see AdminPrivacyTest for the current contract.)
 *
 * employeeDetail() is the single-row counterpart behind the "view seat" modal
 * — the same figures, plus a 6-month breakdown and the seat rate.
 */
class OrgRosterService
{
    /**
     * Stable pseudonymous display id, "EMP-0047".
     *
     * Derived from the membership row id, so it is consistent across page loads
     * and CSV exports without exposing a user id.
     */
    public static function displayId(int $membership_id): string
    {
        return 'EMP-' . str_pad((string) $membership_id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Active/inactive members plus still-outstanding invitations, as one list —
     * the deck shows them in a single table with a status chip.
     */
    public static function roster(Organization $organization, array $filters = []): array
    {
        $cap = $organization->per_employee_session_quota !== null
            ? (int) $organization->per_employee_session_quota
            : null;

        // Batched once for the whole roster rather than per row, matching the
        // "view seat" modal's own cap/last-active figures (employeeDetail()).
        $employee_user_ids = OrganizationMember::where('organization_id', $organization->id)
            ->where('role', OrganizationConstants::ROLE_EMPLOYEE)
            ->pluck('user_id');

        $used_by_user = $employee_user_ids->isEmpty() ? collect() : TherapySession::whereIn('user_id', $employee_user_ids)
            ->active()
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->selectRaw('user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $last_active_by_user = $employee_user_ids->isEmpty() ? collect() : TherapySession::whereIn('user_id', $employee_user_ids)
            ->where('status', TherapistConstants::SESSION_COMPLETED)
            ->selectRaw('user_id, MAX(ended_at) as last_active')
            ->groupBy('user_id')
            ->pluck('last_active', 'user_id')
            ->map(fn ($v) => $v ? Carbon::parse($v)->toIso8601String() : null);

        $members = OrganizationMember::where('organization_id', $organization->id)
            ->with('user:id,email,first_name,last_name')
            ->get()
            ->map(function ($m) use ($cap, $used_by_user, $last_active_by_user) {
                $is_employee = $m->role === OrganizationConstants::ROLE_EMPLOYEE;

                return [
                    'id' => self::displayId($m->id),
                    'member_id' => $m->id,
                    'email' => $m->user?->email,
                    'department' => $m->department,
                    'role' => $m->role,
                    'status' => $m->status,
                    'activated_at' => $m->activated_at?->toDateString(),
                    'source' => 'member',
                    // Deliberate, scoped exception to the roster's usual
                    // contract-only rule — see the class docblock. Employee
                    // seats only; admin/therapist seats carry no session cap.
                    'sessions_used' => $is_employee ? (int) ($used_by_user[$m->user_id] ?? 0) : null,
                    'sessions_cap' => $is_employee ? $cap : null,
                    'last_active' => $is_employee ? ($last_active_by_user[$m->user_id] ?? null) : null,
                ];
            });

        $invites = Invitation::where('organization_id', $organization->id)
            ->where('status', StatusConstants::PENDING)
            ->get()
            ->map(fn ($i) => [
                'id' => 'INV-' . str_pad((string) $i->id, 4, '0', STR_PAD_LEFT),
                'member_id' => null,
                'invitation_id' => $i->id,
                'email' => $i->invitee_email,
                'department' => $i->department,
                'role' => $i->invite_role,
                'status' => OrganizationConstants::MEMBER_INVITED,
                'activated_at' => null,
                'source' => 'invitation',
                'sessions_used' => null,
                'sessions_cap' => $i->invite_role === OrganizationConstants::ROLE_EMPLOYEE ? $cap : null,
                'last_active' => null,
            ]);

        $rows = $members->concat($invites);

        if (!empty($department = $filters['department'] ?? null)) {
            $rows = $rows->where('department', $department);
        }

        if (!empty($status = $filters['status'] ?? null)) {
            $rows = $rows->where('status', $status);
        }

        if (!empty($search = $filters['search'] ?? null)) {
            $needle = strtolower($search);
            $rows = $rows->filter(
                fn ($r) => str_contains(strtolower((string) $r['email']), $needle)
                    || str_contains(strtolower((string) $r['id']), $needle)
            );
        }

        return $rows->values()->all();
    }

    /** The department filter's options, derived from what is actually in use. */
    public static function departments(Organization $organization): array
    {
        return OrganizationMember::where('organization_id', $organization->id)
            ->whereNotNull('department')
            ->distinct()
            ->orderBy('department')
            ->pluck('department')
            ->all();
    }

    public static function scopedMember(Organization $organization, $member_id): OrganizationMember
    {
        $member = OrganizationMember::where('organization_id', $organization->id)
            ->where('id', $member_id)
            ->first();

        if (empty($member)) {
            throw new ModelNotFoundException('Employee not found');
        }

        return $member;
    }

    /**
     * The "view seat" modal (web §03): everything the roster row carries, plus
     * this one member's own usage — the org explicitly chose to show this
     * per-person, unlike every other insight in this controller, which stays
     * anonymised/company-wide. `sessions_this_cycle` is null for non-employee
     * seats (admin/therapist rows), which never carry a session-cap policy.
     */
    public static function employeeDetail(Organization $organization, $member_id): array
    {
        $member = self::scopedMember($organization, $member_id);
        $member->loadMissing('user:id,email');

        $cap_status = $member->role === OrganizationConstants::ROLE_EMPLOYEE && $member->user
            ? SessionCapService::forOrganization($member->user, $organization)
            : null;

        $since = $member->activated_at ?? $member->created_at;
        $months_elapsed = max(1, (int) ceil($since->diffInDays(now()) / 30));

        $completed = TherapySession::where('user_id', $member->user_id)
            ->where('status', TherapistConstants::SESSION_COMPLETED);

        $last_session = (clone $completed)->latest('ended_at')->first();

        $monthly_sessions = collect(range(5, 0))->map(function ($i) use ($member) {
            $month = now()->subMonths($i);

            return [
                'label' => $month->format('M'),
                'count' => TherapySession::where('user_id', $member->user_id)
                    ->where('status', TherapistConstants::SESSION_COMPLETED)
                    ->whereYear('ended_at', $month->year)
                    ->whereMonth('ended_at', $month->month)
                    ->count(),
            ];
        })->values()->all();

        return [
            'id' => self::displayId($member->id),
            'member_id' => $member->id,
            'email' => $member->user?->email,
            'department' => $member->department,
            'role' => $member->role,
            'status' => $member->status,
            'activated_at' => $member->activated_at?->toDateString(),
            'seat_rate' => (int) config('business.employee_seat_rate'),
            'sessions_this_cycle' => $cap_status ? [
                'used' => $cap_status['used'],
                'cap' => $cap_status['cap'],
            ] : null,
            'avg_sessions_per_month' => round((clone $completed)->count() / $months_elapsed, 1),
            'last_active' => $last_session?->ended_at?->toIso8601String(),
            'monthly_sessions' => $monthly_sessions,
        ];
    }

    public function deactivate(Organization $organization, $member_id): OrganizationMember
    {
        $member = self::scopedMember($organization, $member_id);

        if ($member->role === OrganizationConstants::ROLE_ADMIN) {
            throw new InvalidRequestException('Admin seats are managed by TalkAM — contact support to change them.');
        }

        if ($member->status === OrganizationConstants::MEMBER_INACTIVE) {
            throw new InvalidRequestException('That seat is already inactive.');
        }

        $member->update([
            'status' => OrganizationConstants::MEMBER_INACTIVE,
            'deactivated_at' => now(),
        ]);

        return $member->refresh();
    }

    public function reactivate(Organization $organization, $member_id): OrganizationMember
    {
        $member = self::scopedMember($organization, $member_id);

        if ($member->status === OrganizationConstants::MEMBER_ACTIVE) {
            throw new InvalidRequestException('That seat is already active.');
        }

        if ($organization->seatsUsed() >= (int) $organization->seats_licensed) {
            throw new InvalidRequestException(
                'You have no free seats. Increase your seat count from Billing first.'
            );
        }

        $member->update([
            'status' => OrganizationConstants::MEMBER_ACTIVE,
            'deactivated_at' => null,
            'activated_at' => $member->activated_at ?? now(),
        ]);

        return $member->refresh();
    }

    /**
     * Update a seat's own contract details — currently just department,
     * the one editable field the admin dashboard's "view seat" modal never
     * had a way to change after the invite was sent.
     */
    public function updateEmployee(Organization $organization, $member_id, array $data): OrganizationMember
    {
        $member = self::scopedMember($organization, $member_id);

        $validator = Validator::make($data, [
            'department' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $member->update($validator->validated());

        return $member->refresh();
    }

    /* ── Therapist network ──────────────────────────────────────────────── */

    /**
     * The therapists available to this organization.
     *
     * `month_sessions` is a per-THERAPIST total across the whole org — a
     * provider's own workload, not an employee's behaviour, and not attributable
     * to any individual. It is still floored by the cohort rule so a tiny
     * company cannot infer "our one employee saw Dr X four times".
     */
    public static function therapists(Organization $organization, array $filters = []): array
    {
        $member_ids = OrgAggregateService::memberIds($organization);
        $cohort = count($member_ids);
        // The org's own providers — therapists it brought in — always belong to
        // "My Therapists", whether or not they're also explicitly network-added.
        // Keyed by user_id so "own" rows can also carry the membership id —
        // removing an own therapist is a seat deactivation (same as an
        // employee's), which needs the membership id, not the therapist id.
        $own_member_ids = $organization->members()
            ->where('role', OrganizationConstants::ROLE_THERAPIST)
            ->where('status', OrganizationConstants::MEMBER_ACTIVE)
            ->pluck('id', 'user_id');
        // Explicit network membership — the source of truth for "in_network"
        // on every therapist that isn't "own". Specialty/bench-topic overlap
        // is used only to recommend/filter candidates, never to silently
        // grant network membership: an admin-removed therapist must stay
        // removed even if their specialty still matches the bench.
        $network_ids = OrganizationTherapist::where('organization_id', $organization->id)
            ->where('status', OrganizationConstants::NETWORK_ACTIVE)
            ->pluck('therapist_id');
        $month_start = now()->startOfMonth();

        $counts = empty($member_ids) ? collect() : TherapySession::query()
            ->whereIn('user_id', $member_ids)
            ->where('starts_at', '>=', $month_start)
            ->selectRaw('therapist_id, COUNT(*) as total')
            ->groupBy('therapist_id')
            ->pluck('total', 'therapist_id');

        // Cumulative "sessions with your team" (deck's `sessions`, distinct from
        // the table's monthly `monthSessions`): every session this therapist has
        // actually delivered to the org's members, all-time. Completed only —
        // it is a relationship/trust signal, not a booking count.
        $team_counts = empty($member_ids) ? collect() : TherapySession::query()
            ->whereIn('user_id', $member_ids)
            ->where('status', TherapistConstants::SESSION_COMPLETED)
            ->selectRaw('therapist_id, COUNT(*) as total')
            ->groupBy('therapist_id')
            ->pluck('total', 'therapist_id');

        $enough = $cohort >= OrgAggregateService::cohortFloor();

        $therapists = Therapist::status()
            // A Therapist row with no linked user is orphaned data (never a
            // real, displayable provider — no name, no specialty, nothing to
            // show or book) and must never reach the admin UI as a blank card.
            ->whereHas('user')
            ->with('user:id,first_name,last_name,avatar')
            ->withAvg('reviews as rating_avg', 'rating')
            ->withCount('reviews')
            ->get()
            ->map(function ($t) use ($counts, $team_counts, $enough, $own_member_ids, $network_ids) {
                $info = self::specialtyInfoOf($t);
                $specialty = $info['name'];
                $is_own = $own_member_ids->has($t->user_id);

                return [
                    'id' => $t->id,
                    'name' => $t->user?->full_name,
                    'initials' => self::initials($t->user?->full_name),
                    'avatar' => $t->user?->avatar,
                    'specialty' => $specialty,
                    'rating' => $t->rating_avg ? round((float) $t->rating_avg, 1) : null,
                    'reviews' => (int) $t->reviews_count,
                    'is_verified' => !empty($t->verified_at),
                    // A provider the org brought in itself (vs a TalkAM-network therapist).
                    'is_own' => $is_own,
                    // Only present for "own" rows — the OrganizationMember id
                    // needed to deactivate/reactivate their seat. Null for
                    // TalkAM-network therapists, who use add/remove instead.
                    'member_id' => $own_member_ids->get($t->user_id),
                    // Both are company-wide counts, suppressed below the cohort
                    // floor: month_sessions feeds the table's monthly column,
                    // team_sessions the detail modal's cumulative "team sessions".
                    'month_sessions' => $enough ? (int) ($counts[$t->id] ?? 0) : null,
                    'team_sessions' => $enough ? (int) ($team_counts[$t->id] ?? 0) : null,
                    'in_network' => $is_own || $network_ids->contains($t->id),
                ];
            });

        // Snapshot before the request filters narrow the collection below —
        // this is the org's actual therapist-network headcount, independent
        // of whatever specialty/bench_only the caller is currently viewing.
        $network_seats_used = $therapists->where('in_network', true)->count();

        if (!empty($filters['bench_only'] ?? null)) {
            $therapists = $therapists->where('in_network', true);
        }

        if (!empty($specialty = $filters['specialty'] ?? null)) {
            $therapists = $therapists->filter(fn ($t) => $t['specialty'] === $specialty);
        }

        return [
            'therapists' => $therapists->values()->all(),
            'specialties' => $therapists->pluck('specialty')->filter()->unique()->sort()->values()->all(),
            'stats' => [
                // Therapists actually serving the org — NOT `seatsUsed()`,
                // which counts employee/member seats and is unrelated. See
                // the frontend's "My Therapists" page, which independently
                // derives the same number as `mine.length`.
                'seats_used' => $network_seats_used,
                // No therapist-network capacity concept exists yet (this
                // used to silently reuse the employee seat count, which was
                // never actually a therapist limit) — null until a real cap
                // is defined; the UI shows "—" rather than a fabricated one.
                'seats_total' => null,
                'sessions_bundle' => (int) $organization->session_bundle_sessions,
                'sessions_used' => $enough
                    ? (int) TherapySession::whereIn('user_id', $member_ids ?: [0])
                        ->where('starts_at', '>=', $month_start)
                        ->count()
                    : null,
                'next_reset' => now()->addMonth()->startOfMonth()->toDateString(),
                'suppressed' => !$enough,
                'cohort' => $cohort,
            ],
        ];
    }

    /**
     * The full profile behind the "View profile" modal (web §04b): everything
     * the card carries, plus the fields only shown on the detail — focus areas,
     * session formats, next availability and the anonymised review breakdown.
     *
     * Reviews carry NO reviewer identity and no session content, matching the
     * deck's "shown anonymised" rule.
     */
    public static function therapistDetail(Organization $organization, $therapist_id): array
    {
        $member_ids = OrgAggregateService::memberIds($organization);
        $enough = count($member_ids) >= OrgAggregateService::cohortFloor();

        $therapist = Therapist::whereHas('user')
            ->with('user:id,first_name,last_name,avatar,bio')
            ->withAvg('reviews as rating_avg', 'rating')
            ->withCount('reviews')
            ->find($therapist_id);

        if (empty($therapist)) {
            throw new ModelNotFoundException('Therapist not found');
        }

        $own_ids = $organization->members()
            ->where('role', OrganizationConstants::ROLE_THERAPIST)
            ->where('status', OrganizationConstants::MEMBER_ACTIVE)
            ->pluck('user_id');

        // All specialty names (focus areas) plus the first two as the display line.
        $names = self::specialtyNames($therapist);

        $team = empty($member_ids) ? 0 : TherapySession::where('therapist_id', $therapist->id)
            ->whereIn('user_id', $member_ids)
            ->where('status', TherapistConstants::SESSION_COMPLETED)
            ->count();

        $total_reviews = (int) $therapist->reviews_count;
        $review_counts = TherapistReview::where('therapist_id', $therapist->id)
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        return [
            'id' => $therapist->id,
            'name' => $therapist->user?->full_name,
            'initials' => self::initials($therapist->user?->full_name),
            'avatar' => $therapist->user?->avatar,
            'specialty' => $names->take(2)->implode(' · ') ?: null,
            'rating' => $therapist->rating_avg ? round((float) $therapist->rating_avg, 1) : null,
            'reviews' => $total_reviews,
            'is_verified' => !empty($therapist->verified_at),
            'is_own' => $own_ids->contains($therapist->user_id),
            'team_sessions' => $enough ? $team : null,

            // Detail-only fields.
            'focus_areas' => $names->all(),
            'formats' => self::formatLabel($therapist->session_formats),
            'years_experience' => $therapist->years_experience,
            'next_slot' => self::slotLabel(TherapistSlotService::nextSlot($therapist)),
            'reviews_list' => TherapistReview::where('therapist_id', $therapist->id)
                ->latest()
                ->take(6)
                ->get()
                ->map(fn ($r) => [
                    'rating' => (int) $r->rating,
                    'comment' => $r->comment,
                    'when' => $r->created_at?->diffForHumans(),
                ])
                ->all(),
            'rating_breakdown' => collect([5, 4, 3, 2, 1])->map(fn ($s) => [
                'stars' => (string) $s,
                'pct' => $total_reviews ? (int) round(((int) ($review_counts[$s] ?? 0) / $total_reviews) * 100) : 0,
            ])->all(),

            // The therapist's own bio lives on the User row (same field the
            // self-service profile reads — TherapistDirectoryService::profile()).
            'bio' => $therapist->user?->bio,
            'languages' => self::formatLabel($therapist->languages, ', '),
            'response_time' => self::responseTimeLabel($therapist),
        ];
    }

    /**
     * Add a TalkAM-verified therapist to the org's network. Re-adding a
     * previously removed therapist reactivates the same row rather than
     * inserting a duplicate — `organization_id`+`therapist_id` is unique.
     */
    public function addToNetwork(Organization $organization, $therapist_id, User $actor): OrganizationTherapist
    {
        $therapist = Therapist::find($therapist_id);

        if (empty($therapist)) {
            throw new ModelNotFoundException('Therapist not found');
        }

        if (empty($therapist->verified_at)) {
            throw new InvalidRequestException('Only TalkAM-verified therapists can be added to your network.');
        }

        $existing = OrganizationTherapist::where('organization_id', $organization->id)
            ->where('therapist_id', $therapist->id)
            ->first();

        if (!empty($existing) && $existing->status === OrganizationConstants::NETWORK_ACTIVE) {
            throw new InvalidRequestException('This therapist is already in your network.');
        }

        if (!empty($existing)) {
            $existing->update([
                'status' => OrganizationConstants::NETWORK_ACTIVE,
                'added_by' => $actor->id,
                'added_at' => now(),
                'removed_at' => null,
                'removed_by' => null,
            ]);

            return $existing->refresh();
        }

        return OrganizationTherapist::create([
            'organization_id' => $organization->id,
            'therapist_id' => $therapist->id,
            'status' => OrganizationConstants::NETWORK_ACTIVE,
            'added_by' => $actor->id,
            'added_at' => now(),
        ]);
    }

    /**
     * Remove a TalkAM-verified therapist from the org's network. This only
     * covers explicit network membership — an "own" (employer-vouched)
     * therapist is a seat-holding OrganizationMember instead, removed via
     * the same deactivate() employees already use.
     */
    public function removeFromNetwork(Organization $organization, $therapist_id, User $actor): OrganizationTherapist
    {
        $membership = OrganizationTherapist::where('organization_id', $organization->id)
            ->where('therapist_id', $therapist_id)
            ->where('status', OrganizationConstants::NETWORK_ACTIVE)
            ->first();

        if (empty($membership)) {
            throw new InvalidRequestException("This therapist isn't in your network.");
        }

        $membership->update([
            'status' => OrganizationConstants::NETWORK_REMOVED,
            'removed_at' => now(),
            'removed_by' => $actor->id,
        ]);

        return $membership->refresh();
    }

    /** File a capacity request for TalkAM to review — no automatic effect,
     *  just a reviewable record (same shape as group/comment reports). */
    public function requestCapacity(Organization $organization, User $actor, array $data): TherapistCapacityRequest
    {
        $validator = Validator::make($data, [
            'specialty_category_id' => 'nullable|integer|exists:post_categories,id',
            'note' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        return TherapistCapacityRequest::create([
            'organization_id' => $organization->id,
            'requested_by' => $actor->id,
            'specialty_category_id' => $validated['specialty_category_id'] ?? null,
            'note' => $validated['note'] ?? null,
            'status' => StatusConstants::PENDING,
        ]);
    }

    /** Approved-application specialty category names, in order. */
    private static function specialtyNames(Therapist $therapist)
    {
        $application = $therapist->user?->therapistApplications()
            ->where('status', TherapistConstants::STATUS_APPROVED)
            ->with('specialties.category')
            ->latest()
            ->first();

        if (empty($application)) {
            return collect();
        }

        return $application->specialties->map(fn ($s) => $s->category?->name)->filter()->values();
    }

    /** ["video","voice"] → "Video · Voice"; empty → null. */
    private static function formatLabel($formats, string $glue = ' · '): ?string
    {
        $list = collect($formats ?? [])->filter()->map(fn ($f) => ucfirst((string) $f));

        return $list->isNotEmpty() ? $list->implode($glue) : null;
    }

    /**
     * "~2 hrs" / "~1 day" — the average time between a client's session
     * request and this therapist's response (accept or decline), from
     * therapist_session_requests.created_at → responded_at. Real,
     * already-instrumented data (TherapistSessionRequestService sets
     * responded_at on both propose and decline) — null until they've
     * responded to at least one request.
     */
    private static function responseTimeLabel(Therapist $therapist): ?string
    {
        // Computed in PHP rather than a raw SQL diff (TIMESTAMPDIFF is
        // MySQL-only and would break on SQLite, e.g. the test suite) — the
        // row count per therapist is small enough that this is cheap.
        $gaps = TherapistSessionRequest::where('therapist_id', $therapist->id)
            ->whereNotNull('responded_at')
            ->get(['created_at', 'responded_at'])
            ->map(fn ($r) => $r->created_at->diffInSeconds($r->responded_at));

        if ($gaps->isEmpty()) {
            return null;
        }

        $minutes = (int) round($gaps->avg() / 60);

        if ($minutes < 60) {
            return '~' . max(1, $minutes) . ' min';
        }

        $hours = round($minutes / 60);
        if ($hours < 24) {
            return '~' . $hours . ' hr' . ($hours == 1 ? '' : 's');
        }

        $days = round($hours / 24);

        return '~' . $days . ' day' . ($days == 1 ? '' : 's');
    }

    /** A bookable slot → a short "Today 5pm" / "Tomorrow 2pm" / "Aug 8, 4pm" label. */
    private static function slotLabel(?array $slot): ?string
    {
        if (empty($slot['starts_at'])) {
            return null;
        }

        $dt = Carbon::parse($slot['starts_at']);
        $time = $dt->format('g') . ($dt->format('i') !== '00' ? ':' . $dt->format('i') : '') . strtolower($dt->format('a'));

        if ($dt->isToday()) {
            return "Today {$time}";
        }
        if ($dt->isTomorrow()) {
            return "Tomorrow {$time}";
        }

        return $dt->format('M j') . ", {$time}";
    }

    /**
     * A therapist's specialty as both a display string (first two category
     * names) and the full set of specialty category ids used for bench
     * matching. Derived once so the caller doesn't query per field.
     *
     * @return array{name: ?string, ids: array<int>}
     */
    private static function specialtyInfoOf(Therapist $therapist): array
    {
        $application = $therapist->user?->therapistApplications()
            ->where('status', TherapistConstants::STATUS_APPROVED)
            ->with('specialties.category')
            ->latest()
            ->first();

        if (empty($application)) {
            return ['name' => null, 'ids' => []];
        }

        $ids = $application->specialties->pluck('category_id')->filter()->map(fn ($id) => (int) $id)->all();
        $names = $application->specialties->map(fn ($s) => $s->category?->name)->filter()->take(2);

        return [
            'name' => $names->isNotEmpty() ? $names->implode(' · ') : null,
            'ids' => $ids,
        ];
    }

    private static function initials(?string $name): ?string
    {
        if (empty($name)) {
            return null;
        }

        return collect(preg_split('/\s+/', trim($name)))
            ->reject(fn ($w) => in_array(rtrim(strtolower($w), '.'), ['dr', 'mr', 'mrs', 'ms', 'prof'], true))
            ->map(fn ($w) => strtoupper(mb_substr($w, 0, 1)))
            ->take(2)
            ->implode('');
    }
}
