<?php

namespace App\Services\Therapist;

use App\Exceptions\General\InvalidRequestException;
use App\Models\TherapySession;
use Illuminate\Http\Request;

/**
 * AV provider wrapper (Agora — planning doc 08 decision). Nothing outside
 * this service and the AV webhook controller may reference the provider
 * directly, so it stays swappable.
 */
class SessionCallService
{
    /**
     * Channel name per session — the therapy_sessions.channel_ref.
     */
    public function channelFor(TherapySession $session): string
    {
        if (empty($session->channel_ref)) {
            $session->update(['channel_ref' => 'TKSESS-' . $session->uuid]);
        }

        return $session->channel_ref;
    }

    /**
     * Short-lived RTC token for a participant. Real Agora token minting
     * plugs in here once the Agora account/credentials land (infra
     * prerequisite); the interface is final.
     */
    public function token(TherapySession $session, int $user_id): string
    {
        $app_id = config('services.agora.app_id');
        $certificate = config('services.agora.certificate');

        if (empty($app_id) || empty($certificate)) {
            throw new InvalidRequestException("The call service is not configured yet.");
        }

        // Placeholder HMAC token pending the Agora RtcTokenBuilder package —
        // NOT a valid Agora token; swapped during Agora account setup.
        $expires = now()->addMinutes(120)->timestamp;
        $payload = "{$this->channelFor($session)}:{$user_id}:{$expires}";

        return base64_encode($payload . ':' . hash_hmac('sha256', $payload, $certificate));
    }

    /**
     * Webhook authenticity check (HMAC over the raw body).
     */
    public function verifyWebhook(Request $request): bool
    {
        $secret = config('services.agora.webhook_secret');
        if (empty($secret)) {
            return false;
        }

        $signature = $request->header('x-av-signature');

        return !empty($signature)
            && hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature);
    }
}
