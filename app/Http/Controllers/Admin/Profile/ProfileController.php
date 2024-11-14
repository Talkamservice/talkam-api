<?php

namespace App\Http\Controllers\Admin\Profile;

use App\Constants\General\NotificationConstants;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        return view("dashboards.admin.pages.profile.profile", [
            "user" => $user,
        ]);
    }

    public function updatePassword(Request $request)
    {
        $user = auth()->user();
        $validator = Validator::make($request->all(), [
            'old_password' => [
                'required', function ($attribute, $value, $fail) use ($user) {
                    if (!Hash::check($value, $user->password)) {
                        $fail('Old password didn\'t match');
                    }
                },
            ],

            'new_password' => [
                'required', 'different:old_password', 'same:password_confirmation'
            ]

        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        $data = $validator->validated();

        $user->update([
            'password' => Hash::make($data['new_password'])
        ]);

        return back()->with(NotificationConstants::SUCCESS_MSG, "Password updated succesfully");
    }
}
