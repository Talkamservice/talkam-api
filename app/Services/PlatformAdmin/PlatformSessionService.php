<?php

namespace App\Services\PlatformAdmin;

use App\Constants\Business\SessionCoverageConstants;
use App\Constants\Therapist\TherapistConstants;
use App\Models\OrganizationMember;
use App\Models\TherapistReview;
use App\Models\TherapySession;

/**
 * Admin-wide session list (TherapySession has no platform-level index
 * anywhere else — every existing query scopes to a single user/therapist).
 */
class PlatformSessionService
{
    const B2B_COVERAGES = [
        SessionCoverageConstants::ORG_BUNDLE,
        SessionCoverageConstants::ORG_METER,
        SessionCoverageConstants::ORG_EXTERNAL,
    ];

    public static function overview(): array
    {
        $startOfMonth = now()->startOfMonth();
        $now = now();

        $mtd = TherapySession::whereBetween('starts_at', [$startOfMonth, $now]);
        $totalMtd = (clone $mtd)->count();
        $cancelledMtd = (clone $mtd)->where('status', TherapistConstants::SESSION_CANCELLED)->count();

        return [
            "total_mtd" => $totalMtd,
            "date_range_label" => $startOfMonth->format('M j') . '–' . $now->format('j, Y'),
            "live_now" => TherapySession::where('status', TherapistConstants::SESSION_IN_PROGRESS)->count(),
            "cancelled_mtd" => $cancelledMtd,
            "cancellation_rate_percent" => $totalMtd > 0 ? round($cancelledMtd / $totalMtd * 100, 1) : 0,
            "avg_rating" => round((float) (TherapistReview::avg('rating') ?? 0), 1),
        ];
    }

    public static function list(array $filters = [], int $page = 1, int $per_page = 20)
    {
        $query = TherapySession::with([
            'user:id,first_name,last_name,email',
            'therapist.user:id,first_name,last_name',
            'organization:id,name',
            'review:session_id,rating',
        ])->latest('starts_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (($filters['coverage_group'] ?? null) === 'mobile') {
            $query->where('coverage', SessionCoverageConstants::CONSUMER);
        } elseif (($filters['coverage_group'] ?? null) === 'b2b') {
            $query->whereIn('coverage', self::B2B_COVERAGES);
        }

        if (!empty($filters['organization_id'])) {
            $query->where('organization_id', $filters['organization_id']);
        }

        $sessions = $query->paginate($per_page, ['*'], 'page', $page);

        $isB2b = fn (TherapySession $s) => in_array($s->coverage, self::B2B_COVERAGES, true);

        $memberships = self::membershipsFor($sessions->getCollection()->filter($isB2b));

        $sessions->getCollection()->transform(fn (TherapySession $s) => self::row($s, $memberships));

        return $sessions;
    }

    /** Batch-resolves each B2B session's (organization_id, user_id) pair to its
     *  OrganizationMember row, so the client column can reuse the exact same
     *  EMP-XXXX pseudonym already shown on the Business Detail Employees tab
     *  — one query for the whole page instead of N. */
    private static function membershipsFor($b2bSessions): array
    {
        if ($b2bSessions->isEmpty()) {
            return [];
        }

        $pairs = $b2bSessions->map(fn (TherapySession $s) => [$s->organization_id, $s->user_id])->unique(fn ($p) => "{$p[0]}-{$p[1]}");

        $members = OrganizationMember::query()
            ->where(function ($q) use ($pairs) {
                foreach ($pairs as [$orgId, $userId]) {
                    $q->orWhere(fn ($qq) => $qq->where('organization_id', $orgId)->where('user_id', $userId));
                }
            })
            ->get(['id', 'organization_id', 'user_id']);

        $lookup = [];
        foreach ($members as $m) {
            $lookup["{$m->organization_id}-{$m->user_id}"] = $m->id;
        }

        return $lookup;
    }

    private static function row(TherapySession $s, array $memberships): array
    {
        $isB2b = in_array($s->coverage, self::B2B_COVERAGES, true);

        $client = null;
        if ($isB2b) {
            $membershipId = $memberships["{$s->organization_id}-{$s->user_id}"] ?? null;
            $orgName = $s->organization?->name;
            $client = $membershipId
                ? "Employee #" . str_pad((string) $membershipId, 4, '0', STR_PAD_LEFT) . ($orgName ? " ({$orgName})" : "")
                : ($orgName ? "Employee ({$orgName})" : "B2B Employee");
        } elseif ($s->user) {
            $client = trim("{$s->user->first_name} {$s->user->last_name}");
        }

        return [
            "id" => $s->id,
            "reference" => "SES-" . str_pad((string) $s->id, 4, '0', STR_PAD_LEFT),
            "client" => $client,
            "is_b2b" => $isB2b,
            "therapist" => $s->therapist?->user ? trim("{$s->therapist->user->first_name} {$s->therapist->user->last_name}") : null,
            "starts_at" => $s->starts_at?->toDateTimeString(),
            "duration_minutes" => $s->duration_minutes,
            "format" => $s->format,
            "status" => $s->status,
            "coverage" => $s->coverage,
            "amount" => $s->amount,
            "rating" => $s->review?->rating,
        ];
    }
}
