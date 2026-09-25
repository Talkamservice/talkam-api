<?php

namespace App\Services\PlatformAdmin;

use App\Constants\Business\OrganizationConstants;
use App\Constants\System\PlatformAdminConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Dispute;
use App\Models\TherapySession;
use App\Models\User;
use App\Notifications\User\DisputeResolvedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * "Dispute Type" and its longer explanation map onto the model's existing
 * `reason` (short) / `description` (long) columns — no new columns needed
 * for that part. `amount`, `assigned_to` and `escalated_at` ARE new
 * (migration 2026_09_25_000002) — nothing in this codebase tracked a
 * dispute-to-staff assignment or an escalation event before this page.
 */
class PlatformDisputeService
{
    const STATUS_OPEN = "Pending";
    const STATUS_IN_REVIEW = "In Review";
    const STATUS_RESOLVED = "Resolved";
    const STATUS_DISMISSED = "Dismissed";

    const TAB_OPEN = "open";
    const TAB_IN_REVIEW = "in_review";
    const TAB_RESOLVED = "resolved";

    public static function overview(): array
    {
        $resolved = Dispute::whereIn('status', [self::STATUS_RESOLVED, self::STATUS_DISMISSED]);

        $avgHours = (clone $resolved)->whereNotNull('resolved_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours')
            ->value('avg_hours');

        return [
            "open" => Dispute::where('status', self::STATUS_OPEN)->count(),
            "in_review" => Dispute::where('status', self::STATUS_IN_REVIEW)->count(),
            "resolved_mtd" => (clone $resolved)
                ->whereMonth('resolved_at', now()->month)->whereYear('resolved_at', now()->year)->count(),
            "avg_resolution_days" => $avgHours ? round($avgHours / 24, 1) : null,
        ];
    }

    public static function list(string $tab, int $page = 1, int $per_page = 20)
    {
        $query = Dispute::with([
            'reporter:id,first_name,last_name,email',
            'reporter.therapist:id,user_id',
            'reporter.organizationMemberships' => fn ($q) => $q->where('status', OrganizationConstants::MEMBER_ACTIVE),
            'assignee:id,first_name,last_name',
            'resolver:id,first_name,last_name',
        ]);

        match ($tab) {
            self::TAB_IN_REVIEW => $query->where('status', self::STATUS_IN_REVIEW),
            self::TAB_RESOLVED => $query->whereIn('status', [self::STATUS_RESOLVED, self::STATUS_DISMISSED]),
            default => $query->where('status', self::STATUS_OPEN),
        };

        $disputes = $query->latest()->paginate($per_page, ['*'], 'page', $page);
        $disputes->getCollection()->transform(fn (Dispute $d) => self::row($d));

        return $disputes;
    }

    public static function startReview(int $id, int $adminId): Dispute
    {
        $dispute = self::find($id);

        if ($dispute->status !== self::STATUS_OPEN) {
            throw new InvalidRequestException("Only open disputes can be moved to review.");
        }

        $dispute->update(['status' => self::STATUS_IN_REVIEW, 'assigned_to' => $adminId]);
        return $dispute->refresh();
    }

    /** Reassigns to a Super Admin (the most senior platform role) and
     *  timestamps the escalation — there's no separate "support queue"
     *  concept to route to, only the 4 real Spatie platform roles. */
    public static function escalate(int $id): Dispute
    {
        $dispute = self::find($id);

        $superAdmin = User::role(PlatformAdminConstants::ROLE_SUPER_ADMIN)
            ->where('id', '!=', $dispute->assigned_to)
            ->inRandomOrder()
            ->first() ?? User::role(PlatformAdminConstants::ROLE_SUPER_ADMIN)->first();

        if (empty($superAdmin)) {
            throw new InvalidRequestException("No Super Admin available to escalate to.");
        }

        $dispute->update([
            'status' => self::STATUS_IN_REVIEW,
            'assigned_to' => $superAdmin->id,
            'escalated_at' => now(),
        ]);

        return $dispute->refresh();
    }

    public static function resolve(int $id, array $data, int $adminId): Dispute
    {
        $dispute = self::find($id);

        if (in_array($dispute->status, [self::STATUS_RESOLVED, self::STATUS_DISMISSED])) {
            throw new InvalidRequestException("This dispute is already closed.");
        }

        $dispute->update([
            "resolution" => $data['resolution'],
            "status" => $data['status'],
            "resolved_by" => $adminId,
            "resolved_at" => now(),
        ]);

        $dispute->refresh();

        if (!empty($data['notify'])) {
            $recipients = self::partiesFor($dispute);
            if ($recipients->isNotEmpty()) {
                Notification::send(
                    $recipients,
                    new DisputeResolvedNotification(self::reference($dispute), $dispute->status, $dispute->resolution)
                );
            }
        }

        return $dispute;
    }

    public static function reference(Dispute $d): string
    {
        return "DSP-" . str_pad($d->id, 3, '0', STR_PAD_LEFT);
    }

    /** Everyone with a real stake in the outcome: the reporter always, plus
     *  — when the dispute is about a specific session — the session's
     *  client and therapist, since a payment dispute usually involves both
     *  sides of that session, not just whoever filed it. */
    private static function partiesFor(Dispute $d): \Illuminate\Support\Collection
    {
        $parties = collect([$d->reporter])->filter();

        if ($d->subject_type === TherapySession::class) {
            $session = TherapySession::with(['user', 'therapist.user'])->find($d->subject_id);
            $parties = $parties
                ->push($session?->user)
                ->push($session?->therapist?->user)
                ->filter();
        }

        return $parties->unique('id')->values();
    }

    private static function find(int $id): Dispute
    {
        $dispute = Dispute::find($id);
        if (empty($dispute)) {
            throw new ModelNotFoundException("Dispute not found");
        }
        return $dispute;
    }

    private static function row(Dispute $d): array
    {
        $session = $d->subject_type === TherapySession::class ? TherapySession::find($d->subject_id) : null;

        return [
            "id" => $d->id,
            "reference" => self::reference($d),
            "reporter" => $d->reporter ? [
                "id" => $d->reporter->id,
                "name" => trim("{$d->reporter->first_name} {$d->reporter->last_name}"),
                "type" => PlatformUserService::typeOf($d->reporter),
            ] : null,
            "type" => $d->reason,
            "description" => $d->description,
            "amount" => $d->amount !== null ? (float) $d->amount : null,
            "session_ref" => $session ? "SES-" . str_pad($session->id, 4, '0', STR_PAD_LEFT) : null,
            "status" => $d->status,
            "resolution" => $d->resolution,
            "assigned_to" => $d->assignee ? trim("{$d->assignee->first_name} {$d->assignee->last_name}") : null,
            "escalated_at" => $d->escalated_at?->toDateTimeString(),
            "resolved_at" => $d->resolved_at?->toDateTimeString(),
            "created_at" => $d->created_at->toDateTimeString(),
        ];
    }
}
