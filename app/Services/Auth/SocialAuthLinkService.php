<?php

namespace App\Services\Auth;

use App\Constants\General\ApiConstants;
use App\Exceptions\Auth\AuthException;
use App\Exceptions\General\InvalidRequestException;
use App\Models\User;
use App\Services\General\Guzzle\GuzzleService;
use AppleSignIn\ASDecoder;
use Exception;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthLinkService
{
    protected $user;
    protected $provider;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function setProvider(string $provider)
    {
        $this->provider = $provider;
        return $this;
    }

    public function link($token)
    {
        if ($this->provider == "google") {
            return $this->linkGoogle($token);
        }

        if ($this->provider == "facebook") {
            return $this->linkFacebook($token);
        }

        if ($this->provider == "apple") {
            return $this->linkApple($token);
        }

        if ($this->provider == "tiktok") {
            return $this->linkTiktok($token);
        }
    }

    public function linkGoogle(string $token)
    {
        $userData = Socialite::driver($this->provider)->stateless()->userFromToken($token);

        $check = User::whereNot("id", $this->user->id)
            ->where("google_id", $userData->id)
            ->exists();

        if ($check) {
            throw new InvalidRequestException("This account has already been linked");
        }

        $this->user->update([
            "google_id" => $userData->id,
        ]);

        return $this->user->refresh();
    }

    public function linkFacebook(string $token)
    {
        $userData = Socialite::driver($this->provider)->stateless()->userFromToken($token);

        $check = User::whereNot("id", $this->user->id)
            ->where("facebook_id", $userData->id)
            ->exists();

        if ($check) {
            throw new InvalidRequestException("This account has already been linked");
        }

        $this->user->update([
            "facebook_id" => $userData->id,
        ]);

        return $this->user->refresh();
    }

    public function linkApple(string $token)
    {
        $oauth = ASDecoder::getAppleSignInPayload($token);

        $user = $oauth->getUser();

        $check = User::whereNot("id", $this->user->id)
            ->where("apple_user_id", $user)
            ->exists();

        if ($check) {
            throw new InvalidRequestException("This account has already been linked");
        }

        $this->user->update([
            "apple_user_id" => $user,
        ]);

        return $this->user->refresh();
    }

    public function linkTiktok($token)
    {
        try {
            $auth_code = $token;

            $response = (new GuzzleService())
                ->post("https://open-api.tiktok.com/oauth/access_token/", [
                    "client_key" => config("services.tiktok.client_id"),
                    "client_secret" => config("services.tiktok.client_secret"),
                    "code" => $auth_code,
                    "grant_type" => "authorization_code",
                ]);

            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new AuthException($response["message"]["error"] ?? $response["data"]["data"]["description"] ??  "Request failed");
            }

            if (in_array($response["data"]["message"], ["error"])) {
                throw new AuthException($response["data"]["data"]["description"] ??  "Request failed");
            }

            $token = $response["data"]["data"]["access_token"] ?? null;
            $userData = Socialite::driver($this->provider)->userFromToken($token);

            if (empty($userData)) {
                throw new AuthException("Unable to validate token");
            }

            $check = User::whereNot("id", $this->user->id)
                ->where("social_id", $userData->id)
                ->exists();

            if ($check) {
                throw new InvalidRequestException("This account has already been linked");
            }

            $this->user->update([
                "social_id" => $userData->id,
            ]);

            return $this->user->refresh();
        } catch (Exception $e) {
            logger()->error("Error -oauthLoginController", [
                "message" => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function unlink($provider)
    {
        if ($provider == "google") {
            return $this->unlinkGoogle();
        }

        if ($provider == "facebook") {
            return $this->unlinkFacebook();
        }

        if ($provider == "apple") {
            return $this->unlinkApple();
        }

        if ($provider == "tiktok") {
            return $this->unlinkTiktok();
        }
    }

    public function unlinkGoogle()
    {
        $this->user->update([
            "google_id" => null,
        ]);

        return $this->user->refresh();
    }

    public function unlinkFacebook()
    {
        $this->user->update([
            "facebook_id" => null,
        ]);

        return $this->user->refresh();
    }

    public function unlinkApple()
    {
        $this->user->update([
            "apple_user_id" => null,
        ]);

        return $this->user->refresh();
    }

    public function unlinkTikTok()
    {
        $this->user->update([
            "social_id" => null,
        ]);

        return $this->user->refresh();
    }
}
