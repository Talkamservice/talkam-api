<?php

namespace App\Services\Therapist;

use App\Constants\Account\User\UserConstants;
use App\Constants\Therapist\TherapistConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Therapist;
use App\Models\TherapistApplication;
use App\Models\TherapistDocument;
use App\Notifications\Business\NewTherapistAnnouncementNotification;
use App\Notifications\Therapist\TherapistApplicationApprovedNotification;
use App\Notifications\Therapist\TherapistApplicationRejectedNotification;
use App\Services\Business\AdminNotificationGateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin lane: application review. Approval materializes the therapists row
 * and flips the user role to Therapist (the only path that grants the badge).
 */
class TherapistReviewService
{
    public static function getApplication($id): TherapistApplication
    {
        $application = TherapistApplication::find($id);
        if (empty($application)) {
            throw new ModelNotFoundException("Application not found");
        }
        return $application;
    }

    public function approve($application_id): TherapistApplication
    {
        $application = self::getApplication($application_id);

        if (!in_array($application->status, [TherapistConstants::STATUS_SUBMITTED, TherapistConstants::STATUS_IN_REVIEW])) {
            throw new InvalidRequestException("Only submitted applications can be approved.");
        }

        DB::beginTransaction();
        try {
            $user = $application->user;

            $therapist = Therapist::updateOrCreate([
                'user_id' => $user->id,
            ], [
                'credential_type' => $application->credential_type,
                'session_rate' => $application->session_rate,
                'session_formats' => $application->session_formats,
                'session_duration' => $application->session_duration,
                'buffer_minutes' => $application->buffer_minutes,
                'years_experience' => $application->years_experience,
                'verified_at' => now(),
            ]);

            $user->update(['role' => UserConstants::THERAPIST]);

            $application->update([
                'status' => TherapistConstants::STATUS_APPROVED,
                'reviewed_at' => now(),
            ]);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        Notification::send($application->user, new TherapistApplicationApprovedNotification($application));

        // A genuinely NEW therapist joining the network (not a re-approval of an
        // existing one, e.g. a rate-change resubmission) — web §03 Settings →
        // "New therapist announcements". Off by default; platform-wide, cross-org.
        if ($therapist->wasRecentlyCreated) {
            $admins = AdminNotificationGateService::subscribedAdminsPlatformWide(
                'new_therapist_announcements',
                defaultOn: false
            );

            if ($admins->isNotEmpty()) {
                Notification::send($admins, new NewTherapistAnnouncementNotification($therapist));
            }
        }

        return $application->refresh();
    }

    public function reject($application_id, array $data): TherapistApplication
    {
        $validator = Validator::make($data, [
            'reason' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $application = self::getApplication($application_id);

        if (!in_array($application->status, [TherapistConstants::STATUS_SUBMITTED, TherapistConstants::STATUS_IN_REVIEW])) {
            throw new InvalidRequestException("Only submitted applications can be rejected.");
        }

        $application->update([
            'status' => TherapistConstants::STATUS_REJECTED,
            'rejection_reason' => $validator->validated()['reason'],
            'reviewed_at' => now(),
        ]);

        Notification::send($application->user, new TherapistApplicationRejectedNotification($application));

        return $application->refresh();
    }

    public function documentVerdict($document_id, array $data): TherapistDocument
    {
        $validator = Validator::make($data, [
            'status' => ['required', Rule::in([
                TherapistConstants::DOC_STATUS_APPROVED,
                TherapistConstants::DOC_STATUS_REJECTED,
            ])],
            'reason' => 'nullable|string|max:2000|required_if:status,' . TherapistConstants::DOC_STATUS_REJECTED,
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        $document = TherapistDocument::find($document_id);
        if (empty($document)) {
            throw new ModelNotFoundException("Document not found");
        }

        $document->update([
            'status' => $validated['status'],
            'rejection_reason' => $validated['status'] == TherapistConstants::DOC_STATUS_REJECTED
                ? ($validated['reason'] ?? null)
                : null,
        ]);

        return $document->refresh();
    }
}
