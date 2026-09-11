<?php

namespace App\Services\Therapist;

use App\Models\ClientTreatmentPlan;
use App\Models\Therapist;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TreatmentPlanService
{
    /**
     * One plan per (therapist, client) — a repeat POST updates the row.
     */
    public function setOrUpdate(Therapist $therapist, $user_id, array $data): ClientTreatmentPlan
    {
        // Ownership gate: only the client's own therapist sets a plan.
        $client = TherapistClientService::clientOf($therapist, $user_id);

        $validator = Validator::make($data, [
            'total_sessions' => 'required|integer|min:1',
            'progress_status' => ['required', Rule::in(config('therapist.progress_statuses'))],
            'notes' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        return ClientTreatmentPlan::updateOrCreate([
            'therapist_id' => $therapist->id,
            'user_id' => $client->id,
        ], [
            'total_sessions' => $validated['total_sessions'],
            'progress_status' => $validated['progress_status'],
            'notes' => $validated['notes'] ?? null,
        ]);
    }
}
