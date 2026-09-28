<?php

namespace App\Services\PlatformAdmin;

use App\Constants\Business\OrganizationConstants;
use App\Constants\Finance\Payment\PaymentConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Dispute;
use App\Models\HookLog;
use App\Models\OrganizationMember;
use App\Models\Payout;
use App\Models\PlatformSetting;
use App\Models\Therapist;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cross-therapist payout view. Statuses match what PayoutHandlerService
 * (the real Flutterwave webhook handler) actually writes: pending →
 * successful|failed — NOT "completed", which the previous version of this
 * service used and which the webhook handler never writes, so that KPI was
 * silently always 0.
 *
 * "Process" reuses PayoutService::withdraw() wholesale — the exact same
 * balance-check → ledger-debit → Flutterwave-transfer path the automated
 * weekly sweep (payouts:weekly-sweep) already uses in production. This
 * does NOT reimplement any payment logic.
 */
class PlatformPayoutService
{
    const TAB_PENDING = "pending";
    const TAB_PROCESSING = "processing";
    const TAB_COMPLETED = "completed";
    const TAB_B2B_INVITED = "b2b_invited";

    public static function overview(): array
    {
        $pendingTotal = self::pendingBalances()->sum();

        return [
            "next_payout_date" => self::nextPayoutDate(),
            "pending_total" => $pendingTotal,
            "pending_therapist_count" => self::pendingBalances()->filter(fn ($b) => $b > 0)->count(),
            "pending_session_count" => (int) DB::table('therapy_sessions')
                ->whereIn('status', ['confirmed', 'in_progress', 'completed'])
                ->count(),
            "processed_this_month" => (float) Payout::where('status', 'successful')
                ->whereMonth('completed_at', now()->month)->whereYear('completed_at', now()->year)->sum('amount'),
            "runs_this_month" => (int) DB::table('payouts')
                ->where('status', 'successful')
                ->whereMonth('completed_at', now()->month)->whereYear('completed_at', now()->year)
                ->selectRaw('COUNT(DISTINCT DATE(completed_at)) as runs')
                ->value('runs'),
            "open_disputes" => Dispute::where('status', 'Pending')->count(),
            "flutterwave" => self::flutterwaveStatus(),
            "tab_counts" => [
                self::TAB_PENDING => self::pendingBalances()->filter(fn ($b) => $b > 0)->count(),
                self::TAB_PROCESSING => Payout::where('status', 'pending')->count(),
                self::TAB_COMPLETED => Payout::where('status', 'successful')->count(),
                self::TAB_B2B_INVITED => OrganizationMember::where('role', OrganizationConstants::ROLE_THERAPIST)
                    ->where('status', OrganizationConstants::MEMBER_ACTIVE)->count(),
            ],
        ];
    }

    public static function list(string $tab, int $page = 1, int $per_page = 20)
    {
        return match ($tab) {
            self::TAB_PROCESSING => self::payoutRows('pending', $page, $per_page),
            self::TAB_COMPLETED => self::payoutRows('successful', $page, $per_page),
            self::TAB_B2B_INVITED => self::b2bInvitedRows($page, $per_page),
            default => self::pendingRows($page, $per_page),
        };
    }

    /** Reuses the exact production withdrawal path — see class docblock. */
    public static function process(int $therapistId): Payout
    {
        $therapist = Therapist::with('user')->find($therapistId);
        if (empty($therapist)) {
            throw new ModelNotFoundException("Therapist not found");
        }

        return (new \App\Services\Therapist\PayoutService)->withdraw($therapist, 'admin');
    }

    /** Best-effort sweep across every therapist with a positive balance —
     *  one failure (e.g. a stale bank account) doesn't stop the rest,
     *  mirroring WeeklyPayoutSweepCommand's own per-therapist try/catch. */
    public static function processAll(): array
    {
        $balances = self::pendingBalances()->filter(fn ($b) => $b > 0);
        $results = ["processed" => 0, "failed" => 0, "errors" => []];

        foreach ($balances as $therapistId => $balance) {
            try {
                self::process((int) $therapistId);
                $results["processed"]++;
            } catch (\Throwable $e) {
                $results["failed"]++;
                $results["errors"][] = ["therapist_id" => $therapistId, "message" => $e->getMessage()];
            }
        }

        return $results;
    }

    /** Read by the "last run" poll — see ProcessAllPayoutsJob, which
     *  writes this same key. Null until the first batch ever runs. */
    public static function lastRun(): ?array
    {
        $raw = PlatformSetting::get(\App\Jobs\ProcessAllPayoutsJob::SETTING_KEY);
        return $raw ? json_decode($raw, true) : null;
    }

    /** Real per-therapist withdrawable balance — the exact same query
     *  EarningsLedgerService::balance() runs, aggregated across every
     *  therapist in one query instead of N+1 (same status exclusions:
     *  Reversed/Held credits never count). */
    private static function pendingBalances(): Collection
    {
        return DB::table('therapist_wallet_transactions')
            ->whereNotIn('status', ['Reversed', 'Held'])
            ->selectRaw("therapist_id, SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END) - SUM(CASE WHEN type = 'debit' THEN amount ELSE 0 END) as balance")
            ->groupBy('therapist_id')
            ->pluck('balance', 'therapist_id')
            ->map(fn ($b) => round((float) $b, 2));
    }

    private static function pendingRows(int $page, int $per_page)
    {
        $balances = self::pendingBalances()->filter(fn ($b) => $b > 0);

        $therapists = Therapist::whereIn('id', $balances->keys())
            ->with(['user:id,first_name,last_name,email', 'user.payoutAccount'])
            ->get()
            ->keyBy('id');

        $sessionCounts = DB::table('therapy_sessions')
            ->whereIn('therapist_id', $balances->keys())
            ->where('status', 'completed')
            ->selectRaw('therapist_id, COUNT(*) as count')
            ->groupBy('therapist_id')
            ->pluck('count', 'therapist_id');

        $networkTherapistIds = OrganizationMember::where('role', OrganizationConstants::ROLE_THERAPIST)
            ->where('status', OrganizationConstants::MEMBER_ACTIVE)
            ->pluck('user_id')
            ->flip();

        $rows = $balances->map(function ($amount, $therapistId) use ($therapists, $sessionCounts, $networkTherapistIds) {
            $th = $therapists->get($therapistId);
            $sessions = (int) ($sessionCounts[$therapistId] ?? 0);
            $account = $th?->user?->payoutAccount;

            return [
                "therapist_id" => (int) $therapistId,
                "name" => $th?->user ? trim("{$th->user->first_name} {$th->user->last_name}") : null,
                "email" => $th?->user?->email,
                "sessions" => $sessions,
                "rate_per_session" => $sessions > 0 ? round($amount / $sessions, 2) : null,
                "amount_due" => $amount,
                "bank" => $account ? "{$account->bank_name} ****" . substr($account->account_number, -4) : null,
                "has_payout_account" => !empty($account),
                "type" => isset($networkTherapistIds[$th?->user_id]) ? "B2B-Invited" : "Network",
            ];
        })->sortByDesc('amount_due')->values();

        return self::paginateCollection($rows, $page, $per_page);
    }

    private static function payoutRows(string $status, int $page, int $per_page)
    {
        $payouts = Payout::with('therapist.user:id,first_name,last_name,email')
            ->where('status', $status)
            ->latest()
            ->paginate($per_page, ['*'], 'page', $page);

        $payouts->getCollection()->transform(fn (Payout $p) => [
            "id" => $p->id,
            "therapist_id" => $p->therapist_id,
            "name" => $p->therapist?->user ? trim("{$p->therapist->user->first_name} {$p->therapist->user->last_name}") : null,
            "email" => $p->therapist?->user?->email,
            "amount" => (float) $p->amount,
            "provider" => $p->provider,
            "provider_ref" => $p->provider_ref,
            "status" => $p->status,
            "initiated_by" => $p->initiated_by,
            "completed_at" => $p->completed_at?->toDateTimeString(),
            "created_at" => $p->created_at->toDateTimeString(),
        ]);

        return $payouts;
    }

    private static function b2bInvitedRows(int $page, int $per_page)
    {
        $members = OrganizationMember::where('role', OrganizationConstants::ROLE_THERAPIST)
            ->where('status', OrganizationConstants::MEMBER_ACTIVE)
            ->with(['user:id,first_name,last_name,email', 'organization:id,name'])
            ->paginate($per_page, ['*'], 'page', $page);

        $members->getCollection()->transform(fn (OrganizationMember $m) => [
            "id" => $m->id,
            "name" => $m->user ? trim("{$m->user->first_name} {$m->user->last_name}") : null,
            "email" => $m->user?->email,
            "organization" => $m->organization?->name,
            "note" => "Paid directly by employer — not part of TalkAM's payout run.",
        ]);

        return $members;
    }

    private static function paginateCollection(Collection $items, int $page, int $per_page): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            $items->forPage($page, $per_page)->values(),
            $items->count(),
            $per_page,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );
    }

    /** Real, config-driven — matches WeeklyPayoutSweepCommand's own day
     *  check (config('therapist.payout_day'), default wednesday), not a
     *  hardcoded mockup date. */
    private static function nextPayoutDate(): string
    {
        $day = config('therapist.payout_day', 'wednesday');
        $next = now()->next(ucfirst($day));
        if (now()->format('l') === ucfirst($day) && now()->format('H') < 8) {
            $next = now()->startOfDay();
        }
        return $next->toDateString();
    }

    private static function flutterwaveStatus(): array
    {
        $secretKey = config('services.flutterwave.secretKey');
        $mode = str_contains((string) $secretKey, '_TEST') ? "sandbox" : (empty($secretKey) ? "not configured" : "live");

        $lastWebhook = HookLog::where('source', PaymentConstants::FLUTTERWAVE)->latest()->first();

        return [
            "connected" => !empty($secretKey),
            "mode" => $mode,
            "webhooks_active" => !empty($lastWebhook) && $lastWebhook->created_at->gt(now()->subDay()),
            "last_webhook_at" => $lastWebhook?->created_at?->toDateTimeString(),
        ];
    }
}
