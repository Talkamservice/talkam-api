<?php

namespace App\Services\Auth;

use App\Constants\General\ApiConstants;
use App\Exceptions\Auth\AuthException;
use App\Services\General\Guzzle\GuzzleService;
use AppleSignIn\ASDecoder;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class OAuthLoginService
{
    private $token;
    private $provider;

    public function setToken(string $token)
    {
        $this->token = $token;
        return $this;
    }

    public function setProvider(string $provider)
    {
        $this->provider = $provider;
        return $this;
    }

    public function byProvider()
    {
        if ($this->provider == "google") {
            return self::withGoogle();
        }

        if ($this->provider == "apple") {
            return self::withApple();
        }

        if ($this->provider == "facebook") {
            return self::withFacebook();
        }

        if ($this->provider == "tiktok") {
            return self::withTiktok();
        }
    }

    public function withGoogle()
    {
        try {
            $client_id = env('GOOGLE_CLIENT_ID');
            $token = $this->token;

            $response = (new GuzzleService)
                ->getWithQuery("https://oauth2.googleapis.com/tokeninfo?id_token=$token", [
                    "id_token" => $token
                ]);

            if (!in_array($response["status"], [ApiConstants::GOOD_REQ_CODE])) {
                throw new AuthException($response["message"]["error"] ?? "Request failed");
            }

            $payload = $response["data"];

            if (empty($payload)) {
                throw new AuthException("Unable to validate token");
            }

            if ($payload == false) {
                throw new AuthException("The token has expired or is invalid.");
            }

            return $payload;
        } catch (Throwable $e) {
            logger()->error("Error -oauthLoginController", [
                "message" => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function withApple()
    {
        try {
            $oauth = ASDecoder::getAppleSignInPayload($this->token);
            $email = $oauth->getEmail();
            $user = $oauth->getUser();
            $is_valid = $oauth->verifyUser($user);

            if (!$is_valid) {
                throw new AuthException("The token has expired or is invalid.");
            }

            return [
                "name" => explode("@", $email)[0],
                "email" => $email
            ];
        } catch (Throwable $e) {
            logger()->error("Error -oauthLoginController", [
                "message" => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function withFacebook()
    {
        try {
            $token = $this->token;

            $userData = Socialite::driver($this->provider)->userFromToken($token);

            if (empty($payload)) {
                throw new AuthException("Unable to validate token");
            }

            $data = [
                "name" => $userData->name,
                "email" => $userData->email,
                "social_id" => $userData->getId(),
                "avatar" => $userData->avatar ?? null
            ];

            return $data;
        } catch (Throwable $e) {
            logger()->error("Error -oauthLoginController", [
                "message" => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function withTiktok()
    {
        try {
            $token = $this->token;

            $userData = Socialite::driver($this->provider)->userFromToken($token);

            if (empty($payload)) {
                throw new AuthException("Unable to validate token");
            }

            $data = [
                "name" => $userData->name,
                "email" => $userData->email,
                "social_id" => $userData->id,
            ];

            return $data;
        } catch (Throwable $e) {
            logger()->error("Error -oauthLoginController", [
                "message" => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
