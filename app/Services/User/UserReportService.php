<?php

namespace App\Services\User;

use App\Constants\General\StatusConstants;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UserReportService
{
    public function create(User $reporter, array $data): UserReport
    {
        $validator = Validator::make($data, [
            'reported_user_id' => 'required|numeric|exists:users,id',
            'reason' => 'required|string|max:2000',
            'context' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        if ((int) $validated['reported_user_id'] === (int) $reporter->id) {
            throw ValidationException::withMessages([
                'reported_user_id' => ['You cannot report yourself.'],
            ]);
        }

        $open = UserReport::where([
            'reporter_id' => $reporter->id,
            'reported_user_id' => $validated['reported_user_id'],
            'status' => StatusConstants::PENDING,
        ])->exists();

        if ($open) {
            throw ValidationException::withMessages([
                'reported_user_id' => ['You already have an open report for this user.'],
            ]);
        }

        return UserReport::create([
            'reporter_id' => $reporter->id,
            'reported_user_id' => $validated['reported_user_id'],
            'reason' => $validated['reason'],
            'context' => $validated['context'] ?? null,
            'status' => StatusConstants::PENDING,
        ]);
    }
}
