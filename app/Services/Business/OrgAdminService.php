<?php

namespace App\Services\Business;

use App\Constants\Business\OrganizationConstants;
use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\UserReport;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The remaining admin surfaces: trust & safety, the activity log, and company
 * profile settings.
 */
class OrgAdminService
{
    /* ── Trust & Safety ─────────────────────────────────────────────────── */

    /**
     * Reports involving this organization's people, anonymised.
     *
     * TalkAM's Trust & Safety team owns the case; the admin sees that something
     * was raised and where it stands. Deliberately withheld: who filed it, who
     * it names, and the free-text description — a report is often ABOUT the
     * employer's own workplace, and naming the reporter to HR would make the
     * channel unusable.
     */
    public static function safetyReports(Organization $organization): array
    {
        $member_ids = OrgAggregateService::memberIds($organization);

        if (empty($member_ids)) {
            return [];
        }

        return UserReport::where(function ($q) use ($member_ids) {
            $q->whereIn('reporter_id', $member_ids)
                ->orWhereIn('reported_user_id', $member_ids);
        })
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($r) => [
                'id' => 'RPT-' . str_pad((string) $r->id, 4, '0', STR_PAD_LEFT),
                // The category only — never the reporter's own words.
                'category' => $r->reason,
                // Which side of the relationship, never which person.
                'reported_role' => in_array($r->reported_user_id, $member_ids, true)
                    ? OrganizationConstants::ROLE_EMPLOYEE
                    : OrganizationConstants::ROLE_THERAPIST,
                'filed_by' => in_array($r->reporter_id, $member_ids, true)
                    ? 'Employee (anon.)'
                    : 'Therapist (anon.)',
                'status' => $r->status,
                'date' => $r->created_at?->toDateString(),
            ])
            ->values()
            ->all();
    }

    /* ── Activity log ───────────────────────────────────────────────────── */

    /**
     * Admin actions inside this workspace.
     *
     * Scoped to the org's own admins, so one company never sees another's
     * actions. Employee-generated activity is excluded outright — this is an
     * audit trail of administration, not of people.
     */
    public static function activity(Organization $organization, int $limit = 50): array
    {
        $admin_ids = $organization->members()
            ->where('role', OrganizationConstants::ROLE_ADMIN)
            ->pluck('user_id')
            ->all();

        if (empty($admin_ids)) {
            return [];
        }

        return ActivityLog::whereIn('admin_id', $admin_ids)
            // ActivityLog::admin() points at the separate Admin model; org
            // admins are Users, and user() is the relation on the same column.
            ->with('user:id,first_name,last_name')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'actor' => trim(($log->user?->first_name ?? '') . ' ' . ($log->user?->last_name ?? '')) ?: 'TalkAM',
                'action' => $log->description ?? $log->title,
                'activity' => $log->activity,
                'at' => $log->created_at?->toDateTimeString(),
                'when' => $log->created_at?->diffForHumans(),
            ])
            ->values()
            ->all();
    }

    /* ── Company profile ────────────────────────────────────────────────── */

    public function updateProfile(Organization $organization, array $data): Organization
    {
        $validator = Validator::make($data, [
            'name' => 'sometimes|required|string|min:2|max:150',
            'industry' => 'sometimes|nullable|string|max:100',
            'headcount_band' => 'sometimes|nullable|string|max:50',
            'hr_contact_email' => 'sometimes|nullable|email|max:190',
            'logo' => 'sometimes|nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // The domain is NOT editable: it is the tenancy key that invitations and
        // signup uniqueness are built on. Changing it is a support operation.
        $organization->update($validator->validated());

        return $organization->refresh();
    }
}
