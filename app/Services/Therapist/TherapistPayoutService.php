<?php

namespace App\Services\Therapist;

use App\Models\TherapistPayoutAccount;
use App\Models\User;
use App\Services\Finance\PaymentGateways\Flutterwave\FlutterwaveService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TherapistPayoutService
{
    /**
     * Flutterwave bank list, cached. Resolved from the container so tests
     * can bind a mock (the HTTP layer is raw Guzzle, not Http::fake-able).
     */
    public static function banks()
    {
        return Cache::remember('therapist:flutterwave:banks', now()->addHours(6), function () {
            return app(FlutterwaveService::class)->getBanks();
        });
    }

    /**
     * Resolve an account name — verification only, nothing persisted.
     */
    public function verify(array $data): array
    {
        $validated = $this->validateAccountFields($data);

        $resolved = app(FlutterwaveService::class)->resolveAccountNumber(
            $validated['bank_code'],
            $validated['account_number']
        );

        return [
            'bank_code' => $validated['bank_code'],
            'account_number' => $validated['account_number'],
            'account_name' => $resolved['account_name'] ?? null,
        ];
    }

    /**
     * Step 5: save the verified account + the session rate (config-capped).
     * No BVN is read, validated, or stored — Flutterwave owns compliance.
     */
    public function savePayout(User $user, array $data): TherapistPayoutAccount
    {
        $min = config('therapist.session_rate.min');
        $max = config('therapist.session_rate.max');

        $validator = Validator::make($data, [
            'bank_code' => 'required|string',
            'bank_name' => 'required|string',
            'account_number' => 'required|string|min:6|max:20',
            'session_rate' => "required|numeric|min:$min|max:$max",
        ], [
            'session_rate.min' => "The session rate must be at least $min.",
            'session_rate.max' => "The session rate may not be greater than $max.",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $application = TherapistApplicationService::draftFor($user);

        $resolved = app(FlutterwaveService::class)->resolveAccountNumber(
            $validated['bank_code'],
            $validated['account_number']
        );

        DB::beginTransaction();
        try {
            $account = TherapistPayoutAccount::updateOrCreate([
                'user_id' => $user->id,
            ], [
                'bank_code' => $validated['bank_code'],
                'bank_name' => $validated['bank_name'],
                'account_number' => $validated['account_number'],
                'account_name' => $resolved['account_name'] ?? '',
                'provider' => 'flutterwave',
                'verified_at' => now(),
            ]);

            $application->update(['session_rate' => $validated['session_rate']]);

            DB::commit();
            return $account;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    private function validateAccountFields(array $data): array
    {
        $validator = Validator::make($data, [
            'bank_code' => 'required|string',
            'account_number' => 'required|string|min:6|max:20',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
