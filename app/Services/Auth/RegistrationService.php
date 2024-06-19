<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\User\UserService;

class RegistrationService
{

    public $user_service;

    public function __construct()
    {
        $this->user_service = new UserService;
    }

    public function create(array $data): User
    {
        $user = $this->user_service->create($data);
        return $user;
    }

    public  function postRegisterActions(User $user)
    {
        $this->sendWelcomeMessage($user);
    }

    public  function postLoginActions(User $user)
    {
    }

    private function sendWelcomeMessage(User $user)
    {
        // AppMailerService::send([
        //     "data" => [
        //         'recipient_name' => $user->name,
        //         "title" => "Welcome to Mentra",
        //         "message" => "Welcome to Mentra"
        //     ],
        //     "to" => $user->email,
        //     "template" => "emails.template.v1.welcome.index",
        //     "subject" => "Welcome to Mentra",
        // ]);
    }
}
