<?php

namespace App\Services\Therapist;

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

    public static function netFor(TherapySession $session): float
    {
        $share = (float) config('therapist.platform_share_percent');

        return round((float) $session->amount * (1 - $share / 100), 2);
    }

    /**
     * Idempotent: at most one credit per session, ever.
     */
    public static function creditForSession(TherapySession $session): ?TherapistWalletTransaction
    {
        if ($session->status != TherapistConstants::SESSION_COMPLETED) {
            return null;
        }

        return TherapistWalletTransaction::firstOrCreate([
            'therapist_id' => $session->therapist_id,
            'session_id' => $session->id,
            'type' => self::TYPE_CREDIT,
        ], [
            'amount' => self::netFor($session),
            'reference' => "SESSION-{$session->uuid}",
        ]);
    }

    public static function balance(Therapist $therapist): float
    {
        $rows = TherapistWalletTransaction::where('therapist_id', $therapist->id)
            ->where('status', '!=', self::STATUS_REVERSED)
            ->get();

        return round(
            (float) $rows->where('type', self::TYPE_CREDIT)->sum('amount')
                - (float) $rows->where('type', self::TYPE_DEBIT)->sum('amount'),
            2
        );
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
            ],
        ];
    }

    public static function transactions(Therapist $therapist)
    {
        return TherapistWalletTransaction::where('therapist_id', $therapist->id)->latest();
    }
}
