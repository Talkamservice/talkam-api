<?php

namespace App\Http\Controllers\Web;

use App\Constants\General\NotificationConstants;
use App\Constants\General\StatusConstants;
use App\Models\AccountDeactivation;
use App\Models\User;
use Illuminate\Http\Request;

class AccountDeactivationController
{
    public function index()
    {
        return view("auth.deactivation.index");
    }

    public function submit(Request $request)
    {
        $data = $request->validate([
            "email" => "required|string|exists:users,email",
            "reason" => "required|string",
        ]);

        $user = User::where("email", $data["email"])->first();

        $check = AccountDeactivation::where("user_id", $user->id)->first();

        if (!empty($check)) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, "Request has been submitted");
        }

        AccountDeactivation::create([
            "user_id" => $user->id,
            "email" => $user->email,
            "reason" => $data["reason"],
            "status" => StatusConstants::PENDING,
        ]);

        return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, "Request submitted successfully");
    }
}
