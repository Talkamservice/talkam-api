<?php

namespace App\Services\Therapist;

use App\Constants\Post\PostCategoryConstants;
use App\Constants\Therapist\TherapistConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\TherapistApplication;
use App\Models\TherapistAvailability;
use App\Models\TherapistDocument;
use App\Models\TherapistSpecialty;
use App\Models\User;
use App\Notifications\Therapist\TherapistApplicationSubmittedNotification;
use App\Services\Media\FileService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TherapistApplicationService
{
    /**
     * The user's active application (draft/submitted/in_review), if any.
     */
    public static function activeFor(User $user): ?TherapistApplication
    {
        return TherapistApplication::where('user_id', $user->id)
            ->whereIn('status', TherapistConstants::ACTIVE_STATUSES)
            ->latest()
            ->first();
    }

    /**
     * Active draft or a new one. Blocks while an application is under review.
     */
    public static function draftFor(User $user): TherapistApplication
    {
        $active = self::activeFor($user);

        if (!empty($active) && $active->status != TherapistConstants::STATUS_DRAFT) {
            throw new InvalidRequestException("Your application is already under review.");
        }

        return $active ?? TherapistApplication::create([
            'user_id' => $user->id,
            'status' => TherapistConstants::STATUS_DRAFT,
        ]);
    }

    /**
     * Application state + derived per-step completeness (§02 pattern:
     * the app resumes at the first false).
     */
    public static function state(User $user): array
    {
        $application = self::activeFor($user)
            ?? TherapistApplication::where('user_id', $user->id)->latest()->first();

        return [
            'status' => $application?->status,
            'application_id' => $application?->id,
            'rejection_reason' => $application?->rejection_reason,
            'submitted_at' => $application?->submitted_at?->toDateTimeString(),
            'steps' => self::stepCompleteness($application),
        ];
    }

    public static function stepCompleteness(?TherapistApplication $application): array
    {
        if (empty($application)) {
            return array_fill_keys(TherapistConstants::APPLICATION_STEPS, false);
        }

        $document_types = $application->documents()
            ->where('status', '!=', TherapistConstants::DOC_STATUS_REJECTED)
            ->pluck('type')
            ->all();

        return [
            'personal' => !empty($application->credential_type),
            'documents' => empty(array_diff(TherapistConstants::DOCUMENT_TYPES, $document_types)),
            'specialties' => !empty($application->bio) && $application->specialties()->count() > 0,
            'availability' => !empty($application->session_duration)
                && TherapistAvailability::where('user_id', $application->user_id)->exists(),
            'payout' => !empty($application->session_rate)
                && $application->user->payoutAccount()->exists(),
        ];
    }

    public function savePersonal(User $user, array $data): TherapistApplication
    {
        $validator = Validator::make($data, [
            'credential_type' => ['required', 'string', Rule::in(config('therapist.credential_types'))],
            'years_experience' => 'required|integer|min:0|max:80',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $application = self::draftFor($user);

        // Session rate is Payout-step only (decision) — never persisted here.
        $application->update([
            'credential_type' => $validated['credential_type'],
            'years_experience' => $validated['years_experience'],
        ]);

        return $application->refresh();
    }

    public function saveDocument(User $user, array $data): TherapistDocument
    {
        $validator = Validator::make($data, [
            'type' => ['required', Rule::in(TherapistConstants::DOCUMENT_TYPES)],
            'file' => 'required|file|mimes:pdf,jpg,jpeg|max:' . config('therapist.document_max_kb'),
            'expires_at' => 'nullable|date|after:today',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $application = self::draftFor($user);

        DB::beginTransaction();
        try {
            $tmp_path = \App\Helpers\MethodsHelper::putFileInPrivateStorage(
                $validated['file'],
                \App\Constants\Media\FileConstants::TMP_PATH
            );
            $file = (new FileService)->save(
                storage_path("app/" . $tmp_path),
                "therapist-documents",
                null,
                $user->id
            );

            // Re-upload replaces the previous document of the same type.
            $document = TherapistDocument::updateOrCreate([
                'application_id' => $application->id,
                'type' => $validated['type'],
            ], [
                'file_id' => $file->id,
                'status' => TherapistConstants::DOC_STATUS_PENDING,
                'rejection_reason' => null,
                'expires_at' => $validated['expires_at'] ?? null,
            ]);

            DB::commit();
            return $document;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function deleteDocument(User $user, $document_id): void
    {
        $document = TherapistDocument::find($document_id);

        if (empty($document) || $document->application->user_id != $user->id) {
            throw new ModelNotFoundException("Document not found");
        }

        $document->delete();
    }

    public function saveSpecialties(User $user, array $data): TherapistApplication
    {
        $validator = Validator::make($data, [
            'bio' => 'required|string|max:1000',
            'specialties' => 'required|array|min:1',
            'specialties.*' => [
                'required',
                Rule::exists('post_categories', 'id')
                    ->where('type', PostCategoryConstants::TYPE_INTEREST_TOPIC),
            ],
        ], [
            'specialties.*.exists' => 'Each specialty must be an interest topic',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $application = self::draftFor($user);

        DB::beginTransaction();
        try {
            $application->update(['bio' => $validated['bio']]);
            // The bio also serves the §05 search People surface.
            $user->update(['bio' => $validated['bio']]);

            TherapistSpecialty::where('application_id', $application->id)->delete();
            foreach (array_unique($validated['specialties']) as $category_id) {
                TherapistSpecialty::firstOrCreate([
                    'application_id' => $application->id,
                    'category_id' => $category_id,
                ]);
            }

            DB::commit();
            return $application->refresh();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function saveAvailability(User $user, array $data): TherapistApplication
    {
        $validator = Validator::make($data, [
            'session_duration' => ['required', 'integer', Rule::in(config('therapist.session_durations'))],
            'buffer_minutes' => ['required', 'integer', Rule::in(config('therapist.buffers'))],
            'days' => 'required|array|min:1',
            'days.*.day_of_week' => ['required', Rule::in(TherapistConstants::DAYS_OF_WEEK)],
            'days.*.start_time' => 'nullable|date_format:H:i',
            'days.*.end_time' => 'nullable|date_format:H:i|after:days.*.start_time',
            'days.*.active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $application = self::draftFor($user);

        DB::beginTransaction();
        try {
            $application->update([
                'session_duration' => $validated['session_duration'],
                'buffer_minutes' => $validated['buffer_minutes'],
            ]);

            foreach ($validated['days'] as $day) {
                TherapistAvailability::updateOrCreate([
                    'user_id' => $user->id,
                    'day_of_week' => $day['day_of_week'],
                ], [
                    'start_time' => $day['start_time'] ?? null,
                    'end_time' => $day['end_time'] ?? null,
                    'active' => $day['active'],
                ]);
            }

            DB::commit();
            return $application->refresh();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function submit(User $user): TherapistApplication
    {
        $application = self::activeFor($user);

        if (empty($application) || $application->status != TherapistConstants::STATUS_DRAFT) {
            throw new InvalidRequestException("No draft application to submit.");
        }

        $steps = self::stepCompleteness($application);
        foreach ($steps as $step => $complete) {
            if (!$complete) {
                throw ValidationException::withMessages([
                    $step => ["The '$step' step is incomplete."],
                ]);
            }
        }

        $application->update([
            'status' => TherapistConstants::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        Notification::send($user, new TherapistApplicationSubmittedNotification($application));

        return $application->refresh();
    }
}
