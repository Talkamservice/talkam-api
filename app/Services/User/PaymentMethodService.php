<?php

namespace App\Services\User;

use App\Exceptions\General\ModelNotFoundException;
use App\Models\PaymentMethod;
use App\Models\User;

class PaymentMethodService
{
    public static function listFor(User $user)
    {
        return PaymentMethod::where('user_id', $user->id)->latest();
    }

    public function delete(User $user, $id): void
    {
        $method = PaymentMethod::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (empty($method)) {
            throw new ModelNotFoundException("Payment method not found");
        }

        $method->delete();
    }

    /**
     * Opt-in tokenization: called from the session payment handler with the
     * card block of the (verified) Flutterwave charge response. Only the
     * provider token + display fields are stored — never PAN/CVV.
     */
    public static function storeFromChargeResponse(User $user, array $card): ?PaymentMethod
    {
        if (empty($card['token'])) {
            return null;
        }

        [$exp_month, $exp_year] = array_pad(explode('/', $card['expiry'] ?? ''), 2, null);

        return PaymentMethod::firstOrCreate([
            'user_id' => $user->id,
            'token' => $card['token'],
        ], [
            'provider' => 'flutterwave',
            'last4' => $card['last_4digits'] ?? '0000',
            'brand' => trim($card['type'] ?? '') ?: null,
            'exp_month' => $exp_month,
            'exp_year' => $exp_year ? (strlen($exp_year) == 2 ? "20$exp_year" : $exp_year) : null,
        ]);
    }

    public static function getOwned(User $user, $id): PaymentMethod
    {
        $method = PaymentMethod::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (empty($method)) {
            throw new ModelNotFoundException("Payment method not found");
        }

        return $method;
    }
}
