<?php

namespace App\Http\Controllers\Web;

use App\Constants\General\NotificationConstants;
use App\Exceptions\Auth\AuthException;
use App\Exceptions\Auth\PinException;
use App\Services\Auth\V2\PasswordService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The one-click destination for the link in emails.mobile.auth-password-reset.
 * Only exists to turn a signed link into the same {code, password} pair the
 * mobile app's in-app "enter the code" screen already submits to the API
 * (PasswordService::resetPassword) — no separate reset mechanism, no new
 * password-reset concept, just a web form in front of the existing one.
 */
class PasswordResetController
{
    public function index(Request $request)
    {
        return view("auth.passwords.reset-link", [
            "email" => $request->query("email"),
            "code" => $request->query("code"),
        ]);
    }

    public function submit(Request $request)
    {
        $data = $request->validate([
            "email" => "required|email",
            "code" => "required|string",
            "password" => "required|string",
            "password_confirmation" => "required|string",
        ]);

        try {
            app(PasswordService::class)->resetPassword([
                "code" => $data["code"],
                "password" => $data["password"],
                "password_confirmation" => $data["password_confirmation"],
            ]);
        } catch (PinException|AuthException $e) {
            return redirect()->back()->withInput($request->except(["password", "password_confirmation"]))
                ->with(NotificationConstants::ERROR_MSG, $e->getMessage());
        } catch (ValidationException $e) {
            return redirect()->back()->withInput($request->except(["password", "password_confirmation"]))
                ->withErrors($e->validator);
        }

        return view("auth.passwords.reset-link-success");
    }
}
