<?php

namespace App\Services\User;

use App\Constants\Account\User\ConsentConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ConsentService
{
    /**
     * Current state of all consent keys for a user, keyed by consent key.
     * Keys without a row report granted = false.
     */
    public static function state(User $user): array
    {
        $rows = $user->consents()->get()->keyBy('key');

        return collect(ConsentConstants::ALL_KEYS)->mapWithKeys(function ($key) use ($rows) {
            $row = $rows[$key] ?? null;
            return [
                $key => [
                    'key' => $key,
                    'required' => in_array($key, ConsentConstants::REQUIRED_KEYS),
                    'granted' => (bool) ($row?->granted ?? false),
                    'granted_at' => $row?->granted_at?->toDateTimeString(),
                    'revoked_at' => $row?->revoked_at?->toDateTimeString(),
                    'policy_version' => $row?->policy_version,
                ],
            ];
        })->all();
    }

    public function update(User $user, array $data): array
    {
        $validator = Validator::make($data, [
            'consents' => 'required|array:' . implode(',', ConsentConstants::ALL_KEYS),
            'consents.*' => 'boolean',
            'policy_version' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();
        $consents = $data['consents'];

        // Required keys can never be toggled off via the API — that path is
        // account closure through the existing delete-account flow.
        foreach (ConsentConstants::REQUIRED_KEYS as $key) {
            if (array_key_exists($key, $consents) && !$consents[$key]) {
                throw new InvalidRequestException(
                    "The '$key' consent is required to use TalkAM and cannot be withdrawn. To withdraw it, delete your account."
                );
            }
        }

        // First confirm: both required consents must be granted before
        // anything is stored.
        $granted_required = $user->consents()
            ->whereIn('key', ConsentConstants::REQUIRED_KEYS)
            ->where('granted', true)
            ->count();

        if ($granted_required < count(ConsentConstants::REQUIRED_KEYS)) {
            foreach (ConsentConstants::REQUIRED_KEYS as $key) {
                if (empty($consents[$key])) {
                    throw new InvalidRequestException(
                        "The required consents (account operation and session delivery) must be granted."
                    );
                }
            }
        }

        DB::beginTransaction();
        try {
            foreach ($consents as $key => $granted) {
                $row = UserConsent::firstOrNew([
                    'user_id' => $user->id,
                    'key' => $key,
                ]);

                $row->granted = $granted;
                if ($granted) {
                    $row->granted_at = now();
                    $row->revoked_at = null;
                } else {
                    // Go-forward revocation: granted_at stays as the audit trail.
                    $row->revoked_at = now();
                }

                if (!empty($data['policy_version'])) {
                    $row->policy_version = $data['policy_version'];
                }

                $row->save();
            }
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        return self::state($user->refresh());
    }
}
