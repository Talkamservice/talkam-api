<?php

namespace App\Services\Notifications;

use App\Helpers\MethodsHelper;
use App\Jobs\FirebaseMessagingJob;
use App\Models\User;
use App\Services\User\UserService;
use Exception;
use Google\Auth\OAuth2;

class FirebaseNotificationService
{
    public $url, $type, $title, $body, $sound, $fcm_token, $access_token, $image, $icon, $priority;
    public $headers, $configs, $meta_data;
    protected $post_data;

    public function setType(string $type)
    {
        $this->type = $type;
        return $this;
    }

    public function setPriority(string $priority)
    {
        $this->priority = $priority;
        return $this;
    }

    public function setTitle(string $title)
    {
        $this->title = $title;
        return $this;
    }

    public function setImage(string $image)
    {
        $this->image = $image;
        return $this;
    }

    public function setIcon(string $icon)
    {
        $this->icon = $icon;
        return $this;
    }

    public function setBody(string $body)
    {
        $this->body = $body;
        return $this;
    }

    public function setMetadata(array $meta_data)
    {
        $this->meta_data = $meta_data;
        return $this;
    }

    public function setSound(string $sound)
    {
        $this->sound = $sound;
        return $this;
    }

    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    public function setHeaders()
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->access_token,
        ];

        $this->headers = $headers;
        return $this;
    }

    public function setConfigs()
    {
        $configs = [];
        $this->configs = $configs;
        return $this;
    }

    public function byUserId($user_id)
    {
        $user = UserService::getById($user_id);
        $this->fcm_token = $user->fcm_token;

        if (empty($user->fcm_token)) {
            logger("No token found for Email:{$user->email} and ID:{$user->id}");
        }

        $this->parseNotificationPayload();
        return $this;
    }

    public function byUserToken($token)
    {
        $this->fcm_token = $token;
        $this->parseNotificationPayload();
        return $this;
    }

    public function notifyAll($users = null)
    {
        $notification_users = [];
        if (count($users) > 0) {
            $notification_users = $users;
        } else {
            $notification_users = User::where("role", "User")
                ->whereNotNull("fcm_token")->get();
        }


        $post_data = [];
        foreach ($notification_users as $key => $user) {
            $m_fcm_token = $user->fcm_token;
            $post_data[] = $this->parseNotificationPayload($m_fcm_token)[0];
        }

        $this->post_data = $post_data;
        $this->initiate();
    }

    public function parseNotificationPayload($m_fcm_token = null)
    {
        $data = [
            "message" => [
                'token' => $this->fcm_token ?? $m_fcm_token,
                'notification' => [
                    'title' => $this->title,
                    'body' => $this->body,
                ],
                "webpush" => [
                    "headers" => [
                        "Urgency" => $this->priority ?? 'high'
                    ],
                    "notification" => [
                        'click_action' => $this->url,
                        'image' => $this->image,
                        'sound' => $this->sound ?? 'default',
                        'icon' => $this->icon,
                    ],
                ],
                'android' => [
                    'priority' =>  $this->priority ?? 'high'
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10'
                    ]
                ],
                'data' => $this->cleanMetadata($this->meta_data) ?? null,
            ]
        ];
        return $this->post_data = array($data);
    }

    public function cleanMetadata($data)
    {
        $response = [];
        foreach ($data ?? [] as $key => $d) {
            $response[$key] = is_array($d) ? json_encode($d) : "$d";
        }
        return $response;
    }

    public function authenticate()
    {
        try {
            $oauth2 = new OAuth2([
                'clientId' => config("services.firebase.clientId"),
                'authorizationUri' => 'https://accounts.google.com/o/oauth2/auth',
                'redirectUri' => 'urn:ietf:wg:oauth:2.0:oob',
                'tokenCredentialUri' => 'https://oauth2.googleapis.com/token',
                'issuer' => config("services.firebase.clientEmail"),
                'signingKey' => config("services.firebase.privateKey"),
                'signingAlgorithm' => 'RS256',
                'scope' => 'https://www.googleapis.com/auth/cloud-platform',
                'audience' => 'https://oauth2.googleapis.com/token'
            ]);
            $token = $oauth2->fetchAuthToken();
            $this->access_token = $token["access_token"] ?? null;
            logger("Firebase token response", [$token]);
        } catch (Exception $e) {
            logger($e->getMessage(), $e->getTrace());
            throw $e;
        }
    }

    public function notify()
    {
        try {
            $job_payload = [
                "post_data" => $this->post_data,
                "url" => "https://fcm.googleapis.com/v1/projects/catholicpayapp/messages:send",
                "headers" => $this->headers,
                "configs" => $this->configs,
                "meta_data" => $this->meta_data,
            ];

            MethodsHelper::dispatchJob(new FirebaseMessagingJob($job_payload));
        } catch (Exception $e) {
            logger($e->getMessage(), $e->getTrace());
            throw $e;
        }
    }

    public function initiate()
    {
        try {
            $this->authenticate();
            $this->setHeaders();
            $this->setConfigs();
            $this->notify();
            // return $this;
        } catch (\Throwable $th) {
            logger("Error sending firebase message", [
                "error" => $th->getMessage(),
                "trace" => $th->getTrace()
            ]);
            // throw $th;
        }
    }
}
