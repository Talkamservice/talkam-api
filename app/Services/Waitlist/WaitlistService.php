<?php

namespace App\Services\Waitlist;

use App\Exceptions\General\GeneralException;
use App\Exceptions\General\ModelNotFoundException;
use App\Models\Waitlist;
use App\Services\Notifications\AppMailerService;
use App\Services\Provider\HubspotService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class WaitlistService
{
    public static function getById($id)
    {
        $waitlist_service = Waitlist::where("id", $id)->first();

        if (empty($waitlist_service)) {
            throw new ModelNotFoundException("Waitlist not found");
        }

        return $waitlist_service;
    }

    public static function validate(array $data)
    {
        $validator = Validator::make($data, [
            "name" => 'required|string',
            "email" => 'required|string|email',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public function create(array $data)
    {
        $data = self::validate($data);

        $waitlist = Waitlist::where("email", $data["email"])->first();

        if (!empty($waitlist)) {
           throw new GeneralException("This account is already on the waitlist");
        }

        if (empty($waitlist)) {
            $waitlist = Waitlist::create([
                "name" => $data["name"],
                "email" => $data["email"],
            ]);

            // AppMailerService::send([
            //     "data" => [
            //         'email' => $waitlist->email,
            //     ],
            //     "to" => env("ADMIN_EMAIL", config("system.emails.sudo")),
            //     "template" => "emails.user.waitlist",
            //     "subject" => "New Waitlist Member",
            // ]);

            AppMailerService::send([
                "data" => [
                    'email' => $waitlist->email,
                    "recipient_name" => $waitlist->name
                ],
                "to" => $waitlist->email,
                "template" => "emails.waitlist.user-waitlist",
                "subject" => "Talkam: Your Journey Begins Soon!",
            ]);


            // $exists = (new HubspotService)->verifyContact($data["email"]);

            // if (!$exists) {
            //     (new HubspotService)->setCustomerData([
            //         "email" => $data["email"],
            //     ])
            //         ->createContact();
            // }

        }

        return $waitlist;
    }

    public function update(array $data, $id)
    {
        $data = self::validate($data);
        $waitlist_service = self::getById($id);
        $waitlist_service->update($data);
        return $waitlist_service->refresh();
    }

    public function delete($waitlist_service_id)
    {
        $waitlist_service = self::getById($waitlist_service_id);
        $waitlist_service->delete();
    }
}
