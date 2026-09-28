<?php

namespace App\Services\PlatformAdmin;

use App\Constants\Therapist\TherapistConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\TherapistApplication;

/**
 * Thin wrapper over TherapistReviewService (the same service the Blade
 * Admin\Therapist\TherapistReviewController already uses).
 *
 * Note: TherapistConstants::STATUS_IN_REVIEW is a real, distinct status but
 * was previously dead code — nothing anywhere ever set it, so "Under
 * Review" would have been permanently empty. startReview() below is the
 * first real writer of it (see docblock there).
 *
 * "License No." / "Education" in the design mockup have no backing column
 * anywhere in the schema (confirmed by a full-codebase check) — the
 * closest honest equivalents are the uploaded licence *document* (a real
 * file, linkable/viewable) and the application's specialties, used instead.
 */
class PlatformTherapistVerificationService
{
    const TAB_PENDING = "pending";
    const TAB_UNDER_REVIEW = "under_review";
    const TAB_APPROVED = "approved";
    const TAB_DECLINED = "declined";

    public static function overview(): array
    {
        return [
            "pending" => TherapistApplication::where('status', TherapistConstants::STATUS_SUBMITTED)->count(),
            "under_review" => TherapistApplication::where('status', TherapistConstants::STATUS_IN_REVIEW)->count(),
            "approved_mtd" => TherapistApplication::where('status', TherapistConstants::STATUS_APPROVED)
                ->whereMonth('reviewed_at', now()->month)->whereYear('reviewed_at', now()->year)->count(),
            "declined_mtd" => TherapistApplication::where('status', TherapistConstants::STATUS_REJECTED)
                ->whereMonth('reviewed_at', now()->month)->whereYear('reviewed_at', now()->year)->count(),
        ];
    }

    public static function list(?string $tab, int $page = 1, int $per_page = 20)
    {
        $query = TherapistApplication::with([
            'user:id,first_name,last_name,email',
            'documents.file:id,name,path',
            'specialties.category:id,name',
        ]);

        match ($tab) {
            self::TAB_UNDER_REVIEW => $query->where('status', TherapistConstants::STATUS_IN_REVIEW),
            self::TAB_APPROVED => $query->where('status', TherapistConstants::STATUS_APPROVED),
            self::TAB_DECLINED => $query->where('status', TherapistConstants::STATUS_REJECTED),
            default => $query->where('status', TherapistConstants::STATUS_SUBMITTED),
        };

        $applications = $query->latest('submitted_at')->paginate($per_page, ['*'], 'page', $page);
        $applications->getCollection()->transform(fn (TherapistApplication $a) => self::row($a));

        return $applications;
    }

    /**
     * Real first use of STATUS_IN_REVIEW — clicking "Review Application"
     * moves it out of the Pending queue into Under Review so staff can see
     * who's actively being looked at, not just who's waiting.
     */
    public static function startReview(int $id): array
    {
        $application = TherapistApplication::find($id);
        if (empty($application)) {
            throw new ModelNotFoundException("Application not found");
        }

        if ($application->status !== TherapistConstants::STATUS_SUBMITTED) {
            throw new InvalidRequestException("Only pending applications can be moved to review.");
        }

        $application->update(['status' => TherapistConstants::STATUS_IN_REVIEW]);
        return self::row($application->refresh()->load(['user:id,first_name,last_name,email', 'documents.file:id,name,path', 'specialties.category:id,name']));
    }

    private static function row(TherapistApplication $a): array
    {
        return [
            "id" => $a->id,
            "user" => $a->user ? [
                "id" => $a->user->id,
                "name" => trim("{$a->user->first_name} {$a->user->last_name}"),
                "email" => $a->user->email,
            ] : null,
            "credential_type" => $a->credential_type,
            "years_experience" => $a->years_experience,
            "status" => $a->status,
            "submitted_at" => $a->submitted_at?->toDateTimeString(),
            "reviewed_at" => $a->reviewed_at?->toDateTimeString(),
            "rejection_reason" => $a->rejection_reason,
            "specialties" => $a->specialties->map(fn ($s) => $s->category?->name)->filter()->values(),
            "documents" => $a->documents->map(fn ($d) => [
                "id" => $d->id,
                "type" => $d->type,
                "status" => $d->status,
                "rejection_reason" => $d->rejection_reason,
                "file_name" => $d->file?->name,
                "file_url" => $d->file?->url(),
            ]),
        ];
    }
}
