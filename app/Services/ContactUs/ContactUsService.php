<?php

namespace App\Services\ContactUs;

use App\Exceptions\General\ModelNotFoundException;
use App\Models\ContactUs;
use App\Services\Notifications\AppMailerService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ContactUsService
{

    public static function getById($id): ContactUs
    {
        $contact_us = ContactUs::find($id);
        if (empty($contact_us)) {
            throw new ModelNotFoundException("Contact info not found");
        }
        return $contact_us;
    }

    public static function validate($data, $id = null)
    {
        $validator = Validator::make($data, [
            "full_name" => "bail|required|string",
            "email" => "bail|required|string|email",
            "phone_number" => "bail|required|numeric",
            "message" => "bail|nullable|string",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public  function store(array $data)
    {
        $data = self::validate($data);
        $contact_us =  ContactUs::create($data);

        AppMailerService::send([
            "data" => [
                'email' => $data["email"],
                'name' => $data["full_name"],
                'phone_number' => $data["phone_number"],
                'message' => $data["message"] ?? null,
            ],
            "to" => env("ADMIN_EMAIL", "hello@yourmentra.com"),
            "template" => "emails.user.contact_us_mail",
            "subject" => "Contact Us Form Submission",
        ]);

        return $contact_us;
    }

    public function update(array $data, $id)
    {
        $data = self::validate($data, $id);
        $contact_us = self::getById($id);

        $contact_us->update($data);
        return $contact_us->refresh();
    }

    public function delete($contact_us_id)
    {
        $contact_us = self::getById($contact_us_id);
        $contact_us->delete();
    }
}
