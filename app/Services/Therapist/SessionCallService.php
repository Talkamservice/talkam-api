<?php

namespace App\Services\Therapist;

use App\Exceptions\General\InvalidRequestException;
use App\Models\TherapySession;
use BoogieFromZk\AgoraToken\RtcTokenBuilder2;
use Illuminate\Http\Request;

/**
 * AV provider wrapper (Agora — planning doc 08 decision). Nothing outside
 * this service and the AV webhook controller may reference the provider
 * directly, so it stays swappable.
 */
class SessionCallService
{
    /** How long a join token stays valid — comfortably covers the join
     *  window plus the session's own duration for a slow/rejoining client. */
    private const TOKEN_TTL_SECONDS = 4 * 60 * 60;

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
     * Short-lived RTC token for a participant, minted with Agora's own
     * token-builder algorithm (AccessToken2 / Token 007). Both the client
     * and the therapist publish their own audio/video, so both get the
     * publisher role — there's no host/audience distinction in a 1:1
     * therapy session.
     */
    public function token(TherapySession $session, int $user_id): string
    {
        $app_id = config('services.agora.app_id');
        $certificate = config('services.agora.certificate');

        if (empty($app_id) || empty($certificate)) {
            throw new InvalidRequestException("The call service is not configured yet.");
        }

        return RtcTokenBuilder2::buildTokenWithUid(
            $app_id,
            $certificate,
            $this->channelFor($session),
            $user_id,
            RtcTokenBuilder2::ROLE_PUBLISHER,
            self::TOKEN_TTL_SECONDS,
            self::TOKEN_TTL_SECONDS
        );
    }

    /**
     * Webhook authenticity check — Agora Notifications signs the raw body
     * with HMAC-SHA256, hex-encoded, in the `Agora-Signature-V2` header (a
     * SHA1 `Agora-Signature` also exists; SHA256 is the stronger of the two
     * and sufficient on its own). The secret comes from Console → Projects →
     * [project] → Edit → All Features → Notifications → Secret.
     */
    public function verifyWebhook(Request $request): bool
    {
        $secret = config('services.agora.webhook_secret');
        if (empty($secret)) {
            return false;
        }

        $signature = $request->header('Agora-Signature-V2');

        return !empty($signature)
            && hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature);
    }
}
