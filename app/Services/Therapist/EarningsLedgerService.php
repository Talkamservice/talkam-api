<?php

namespace App\Services\Therapist;

use App\Constants\Business\SessionCoverageConstants as Cov;
use App\Constants\General\StatusConstants;
use App\Constants\Therapist\TherapistConstants;
use App\Models\Therapist;
use App\Models\TherapistWalletTransaction;
use App\Models\TherapySession;

/**
 * The therapist earnings ledger — one credit per completed session, one
 * debit per payout. Balance is always derived; nothing is stored.
 */
class EarningsLedgerService
{
    const TYPE_CREDIT = 'credit';
    const TYPE_DEBIT = 'debit';
    const STATUS_REVERSED = 'Reversed';
    // A B2B postpay (org_meter) credit that is earned but withheld until the
    // employer settles that month's invoice. Excluded from balance until released.
    const STATUS_HELD = 'Held';

    public static function netFor(TherapySession $session): float
    {
        $share = (float) config('therapist.platform_share_percent');

        return round((float) $session->amount * (1 - $share / 100), 2);
    }

    /**
     * Idempotent: at most one credit per session, ever.
     *
     * Coverage-aware (web §09): a consumer/prepay session credits immediately;
     * a postpay (org_meter) session is credited but HELD until the employer
     * settles; the company's own therapist (org_external) is never credited here.
     * Consumer sessions (coverage defaults to CONSUMER) behave exactly as before.
     */
    public static function creditForSession(TherapySession $session): ?TherapistWalletTransaction
    {
        if ($session->status != TherapistConstants::SESSION_COMPLETED) {
            return null;
        }

        $coverage = $session->coverage ?? Cov::CONSUMER;

        // The company's own therapist is settled outside TalkAM — no ledger credit.
        if ($coverage === Cov::ORG_EXTERNAL) {
            return null;
        }

        $attributes = [
            'amount' => self::earnedFor($session, $coverage),
            'reference' => "SESSION-{$session->uuid}",
        ];

        // Postpay: earned now, but withheld until the employer settles. Consumer
        // and prepay leave status unset so it keeps the default (Completed).
        if ($coverage === Cov::ORG_METER) {
            $attributes['status'] = self::STATUS_HELD;
        }

        return TherapistWalletTransaction::firstOrCreate([
            'therapist_id' => $session->therapist_id,
            'session_id' => $session->id,
            'type' => self::TYPE_CREDIT,
        ], $attributes);
    }

    /**
     * What the therapist earns for a session. A network therapist on a B2B
     * session earns the flat, configurable network rate (funded by the org),
     * not their consumer rate.
     */
    private static function earnedFor(TherapySession $session, string $coverage): float
    {
        if (in_array($coverage, [Cov::ORG_BUNDLE, Cov::ORG_METER])) {
            return (float) config('business.network_session_payout');
        }

        return self::netFor($session);
    }

    public static function balance(Therapist $therapist): float
    {
        $rows = TherapistWalletTransaction::where('therapist_id', $therapist->id)
            ->whereNotIn('status', [self::STATUS_REVERSED, self::STATUS_HELD])
            ->get();

        return round(
            (float) $rows->where('type', self::TYPE_CREDIT)->sum('amount')
                - (float) $rows->where('type', self::TYPE_DEBIT)->sum('amount'),
            2
        );
    }

    /**
     * Money a therapist has earned on postpay B2B sessions but can't withdraw
     * yet — it releases when the employer settles that month's invoice. Powers
     * the "Pending employer settlement" figure on the Earnings screen.
     */
    public static function pendingSettlement(Therapist $therapist): array
    {
        $held = TherapistWalletTransaction::where('therapist_id', $therapist->id)
            ->where('type', self::TYPE_CREDIT)
            ->where('status', self::STATUS_HELD)
            ->get();

        return [
            'sessions' => $held->count(),
            'amount' => round((float) $held->sum('amount'), 2),
        ];
    }

    /**
     * Release the held credits for an org's postpay sessions in a settled period
     * (called when the month-end invoice is paid) — flips them to available so
     * the next weekly (Wednesday) sweep pays them out. Returns the count released.
     */
    public static function releaseHeldCredits(int $organization_id, $from, $to): int
    {
        // Invoice period columns are date-cast (midnight); widen to whole days so
        // a session late on the last day isn't missed.
        $from = \Illuminate\Support\Carbon::parse($from)->startOfDay();
        $to = \Illuminate\Support\Carbon::parse($to)->endOfDay();

        $session_ids = TherapySession::where('organization_id', $organization_id)
            ->where('coverage', Cov::ORG_METER)
            ->whereBetween('starts_at', [$from, $to])
            ->pluck('id');

        if ($session_ids->isEmpty()) {
            return 0;
        }

        return TherapistWalletTransaction::whereIn('session_id', $session_ids)
            ->where('type', self::TYPE_CREDIT)
            ->where('status', self::STATUS_HELD)
            ->update(['status' => StatusConstants::COMPLETED]);
    }

    /**
     * Pending payout = net value of confirmed-but-not-completed sessions
     * (derived — those sessions have no ledger rows yet).
     */
    public static function pendingPayout(Therapist $therapist): array
    {
        $sessions = TherapySession::where('therapist_id', $therapist->id)
            ->whereIn('status', [
                TherapistConstants::SESSION_CONFIRMED,
                TherapistConstants::SESSION_IN_PROGRESS,
            ])
            ->get();

        $share = (float) config('therapist.platform_share_percent');

        return [
            'sessions' => $sessions->count(),
            'amount' => round((float) $sessions->sum('amount') * (1 - $share / 100), 2),
        ];
    }

    public static function dashboard(Therapist $therapist): array
    {
        $credits = TherapistWalletTransaction::where('therapist_id', $therapist->id)
            ->where('type', self::TYPE_CREDIT)
            ->where('status', '!=', self::STATUS_REVERSED)
            ->get();

        $week_credits = $credits->filter(fn ($row) => $row->created_at->gte(now()->startOfWeek()));
        $month_credits = $credits->filter(fn ($row) => $row->created_at->gte(now()->startOfMonth()));

        $chart = collect(range(6, 0))->map(function ($days_ago) use ($credits) {
            $date = now()->subDays($days_ago)->toDateString();
            return [
                'date' => $date,
                'amount' => round((float) $credits->filter(
                    fn ($row) => $row->created_at->toDateString() == $date
                )->sum('amount'), 2),
            ];
        })->values()->all();

        $completed_this_week = TherapySession::where('therapist_id', $therapist->id)
            ->where('status', TherapistConstants::SESSION_COMPLETED)
            ->where('starts_at', '>=', now()->startOfWeek())
            ->count();

        return [
            'balance' => self::balance($therapist),
            'currency' => config('therapist.session_rate.currency'),
            'totals' => [
                'this_week' => round((float) $week_credits->sum('amount'), 2),
                'this_month' => round((float) $month_credits->sum('amount'), 2),
                'all_time' => round((float) $credits->sum('amount'), 2),
            ],
            'chart' => $chart,
            'tiles' => [
                'sessions_this_week' => $completed_this_week,
                'avg_per_session' => $credits->count() > 0
                    ? round((float) $credits->avg('amount'), 2)
                    : 0,
                'pending_payout' => self::pendingPayout($therapist),
                'pending_settlement' => self::pendingSettlement($therapist),
            ],
            // The web §04 Earnings screen's "Recent payouts" list.
            'recent_payouts' => \App\Models\Payout::where('therapist_id', $therapist->id)
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn ($payout) => [
                    'id' => $payout->id,
                    'date' => $payout->completed_at?->toDateString() ?? $payout->created_at?->toDateString(),
                    'amount' => round((float) $payout->amount, 2),
                    'status' => $payout->status,
                ])
                ->all(),
        ];
    }

    public static function transactions(Therapist $therapist)
    {
        return TherapistWalletTransaction::where('therapist_id', $therapist->id)->latest();
    }
}
