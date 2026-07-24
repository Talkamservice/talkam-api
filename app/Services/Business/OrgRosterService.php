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
use App\Models\Therapist;
use App\Models\TherapySession;

/**
 * Seat administration for the admin dashboard.
 *
 * The roster is deliberately CONTRACT data only — who holds a seat, in which
 * department, at what status. It carries no session count, no last-active
 * timestamp and no wellbeing signal of any kind; those are behaviour, and
 * behaviour is only ever exposed company-wide through OrgAggregateService.
 * (planning-docs/web-api/03-admin-dashboard.md §0)
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
        $members = OrganizationMember::where('organization_id', $organization->id)
            ->with('user:id,email,first_name,last_name')
            ->get()
            ->map(fn ($m) => [
                'id' => self::displayId($m->id),
                'member_id' => $m->id,
                'email' => $m->user?->email,
                'department' => $m->department,
                'role' => $m->role,
                'status' => $m->status,
                'activated_at' => $m->activated_at?->toDateString(),
                'source' => 'member',
            ]);

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
        $bench = collect($organization->bench_topics ?? []);
        $month_start = now()->startOfMonth();

        $counts = empty($member_ids) ? collect() : TherapySession::query()
            ->whereIn('user_id', $member_ids)
            ->where('starts_at', '>=', $month_start)
            ->selectRaw('therapist_id, COUNT(*) as total')
            ->groupBy('therapist_id')
            ->pluck('total', 'therapist_id');

        $enough = $cohort >= OrgAggregateService::cohortFloor();

        $therapists = Therapist::status()
            ->with('user:id,first_name,last_name,avatar')
            ->withAvg('reviews as rating_avg', 'rating')
            ->withCount('reviews')
            ->get()
            ->map(function ($t) use ($counts, $enough, $bench) {
                $specialty = self::specialtyOf($t);

                return [
                    'id' => $t->id,
                    'name' => $t->user?->full_name,
                    'initials' => self::initials($t->user?->full_name),
                    'avatar' => $t->user?->avatar,
                    'specialty' => $specialty,
                    'rating' => $t->rating_avg ? round((float) $t->rating_avg, 1) : null,
                    'reviews' => (int) $t->reviews_count,
                    'is_verified' => !empty($t->verified_at),
                    // Only ever a company-wide count, and only above the floor.
                    'month_sessions' => $enough ? (int) ($counts[$t->id] ?? 0) : null,
                    'in_network' => $bench->isEmpty() || $bench->contains(fn ($key) => self::matchesBench($key, $specialty)),
                ];
            });

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
                'seats_used' => $organization->seatsUsed(),
                'seats_total' => (int) $organization->seats_licensed,
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

    private static function matchesBench(string $key, ?string $specialty): bool
    {
        if (empty($specialty)) {
            return false;
        }

        return str_contains(strtolower($specialty), strtolower($key));
    }

    private static function specialtyOf(Therapist $therapist): ?string
    {
        $application = $therapist->user?->therapistApplications()
            ->where('status', TherapistConstants::STATUS_APPROVED)
            ->with('specialties.category')
            ->latest()
            ->first();

        if (empty($application)) {
            return null;
        }

        $names = $application->specialties->map(fn ($s) => $s->category?->name)->filter()->take(2);

        return $names->isNotEmpty() ? $names->implode(' · ') : null;
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
