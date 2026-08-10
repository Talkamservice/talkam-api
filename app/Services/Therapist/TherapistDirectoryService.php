<?php

namespace App\Services\Therapist;

use App\Constants\Business\OrganizationConstants;
use App\Constants\Therapist\TherapistConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\OrganizationMember;
use App\Models\OrganizationTherapist;
use App\Models\Therapist;
use App\Models\TherapistApplication;
use App\Models\TherapistReview;
use App\Models\TherapistSpecialty;
use App\Models\TherapySession;
use App\Models\User;
use Illuminate\Support\Collection;

class TherapistDirectoryService
{
    public static function list(array $data = [], ?User $user = null)
    {
        // Open consumer directory: TalkAM-verified therapists only. Business
        // therapists a company brought in are unverified (no verified_at) and
        // normally serve only their employer's team via the org roster — but
        // that team IS this directory for a business-employed caller, so
        // their own org's therapists are unioned in below (§ booking picker).
        $org_ids = self::orgEligibleIds($user);

        $builder = Therapist::with('user')
            ->status()
            ->where(function ($q) use ($org_ids) {
                $q->whereNotNull('verified_at');
                if ($org_ids->isNotEmpty()) {
                    $q->orWhereIn('id', $org_ids);
                }
            })
            ->withAvg('reviews as rating_avg', 'rating')
            ->withCount('reviews');

        if (!empty($key = $data['search'] ?? null)) {
            $builder = $builder->search($key);
        }

        if (!empty($key = $data['specialty_id'] ?? null)) {
            $builder = $builder->whereHas('user', function ($q) use ($key) {
                $q->whereHas('therapistApplications', function ($app) use ($key) {
                    $app->where('status', TherapistConstants::STATUS_APPROVED)
                        ->whereHas('specialties', fn ($s) => $s->where('category_id', $key));
                });
            });
        }

        $builder = match ($data['sort'] ?? null) {
            'rating' => $builder->orderByDesc('rating_avg'),
            'price' => $builder->orderBy('session_rate'),
            default => $builder->latest(),
        };

        return $builder;
    }

    public static function getById($id, ?User $user = null): Therapist
    {
        // Verified-only, plus — for a business-employed caller — their own
        // org's therapists (own + network), unverified or not. See list().
        $org_ids = self::orgEligibleIds($user);

        $therapist = Therapist::with('user')
            ->where(function ($q) use ($org_ids) {
                $q->whereNotNull('verified_at');
                if ($org_ids->isNotEmpty()) {
                    $q->orWhereIn('id', $org_ids);
                }
            })
            ->find($id);

        if (empty($therapist)) {
            throw new ModelNotFoundException("Therapist not found");
        }
        return $therapist;
    }

    /**
     * The therapist ids a business-employed user's own organization has made
     * available to them: therapists the org brought in as "own" (settled
     * outside TalkAM) plus its explicit network roster. Empty for anyone who
     * isn't an active employee — the consumer directory is unaffected.
     */
    private static function orgEligibleIds(?User $user): Collection
    {
        if (empty($user)) {
            return collect();
        }

        $organization_id = OrganizationMember::where('user_id', $user->id)
            ->where('role', OrganizationConstants::ROLE_EMPLOYEE)
            ->where('status', OrganizationConstants::MEMBER_ACTIVE)
            ->value('organization_id');

        if (empty($organization_id)) {
            return collect();
        }

        $own_user_ids = OrganizationMember::where('organization_id', $organization_id)
            ->where('role', OrganizationConstants::ROLE_THERAPIST)
            ->where('status', OrganizationConstants::MEMBER_ACTIVE)
            ->pluck('user_id');

        $own_ids = Therapist::whereIn('user_id', $own_user_ids)->pluck('id');

        $network_ids = OrganizationTherapist::where('organization_id', $organization_id)
            ->where('status', OrganizationConstants::NETWORK_ACTIVE)
            ->pluck('therapist_id');

        return $own_ids->merge($network_ids)->unique();
    }

    public static function card(Therapist $therapist): array
    {
        return [
            'id' => $therapist->id,
            'name' => $therapist->user?->full_name,
            'username' => $therapist->user?->username,
            'avatar' => $therapist->user?->avatar,
            'is_verified' => !empty($therapist->verified_at),
            'credential_type' => $therapist->credential_type,
            'years_experience' => $therapist->years_experience,
            'session_rate' => $therapist->session_rate,
            'session_formats' => $therapist->session_formats,
            'rating' => round((float) ($therapist->rating_avg ?? 0), 1),
            'reviews_count' => (int) ($therapist->reviews_count ?? 0),
            'specialties' => self::specialties($therapist),
            'next_slot' => TherapistSlotService::nextSlot($therapist),
        ];
    }

    public static function profile(Therapist $therapist): array
    {
        $completed_sessions = TherapySession::where('therapist_id', $therapist->id)
            ->where('status', TherapistConstants::SESSION_COMPLETED)
            ->count();

        return array_merge(self::card($therapist->loadAvg('reviews as rating_avg', 'rating')->loadCount('reviews')), [
            'bio' => $therapist->user?->bio,
            'session_duration' => $therapist->session_duration,
            'buffer_minutes' => $therapist->buffer_minutes,
            'completed_sessions' => $completed_sessions,
            'ratings_histogram' => self::histogram($therapist),
        ]);
    }

    public static function specialties(Therapist $therapist): array
    {
        $application = TherapistApplication::where('user_id', $therapist->user_id)
            ->where('status', TherapistConstants::STATUS_APPROVED)
            ->latest()
            ->first();

        if (empty($application)) {
            return [];
        }

        return TherapistSpecialty::with('category')
            ->where('application_id', $application->id)
            ->get()
            ->map(fn ($row) => ['id' => $row->category?->id, 'name' => $row->category?->name])
            ->values()
            ->all();
    }

    public static function histogram(Therapist $therapist): array
    {
        $counts = TherapistReview::where('therapist_id', $therapist->id)
            ->selectRaw('rating, count(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        return collect(range(1, 5))
            ->mapWithKeys(fn ($star) => [$star => (int) ($counts[$star] ?? 0)])
            ->all();
    }
}
