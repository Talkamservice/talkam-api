<?php

namespace App\Http\Controllers\Web;

use App\Constants\Account\User\UserConstants;
use App\Constants\General\NotificationConstants;
use App\Constants\General\StatusConstants;
use App\Constants\Invitation\InvitationConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\Invitation\InviteException;
use App\Exceptions\User\UserException;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Services\Auth\RegistrationService;
use App\Services\Invitation\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InviteController extends Controller
{
    function sendInvite(Request $request)
    {
        $data = $request->validate([
            "emails" => "required|string",
            "role_id" => "required|exists:roles,id",
            "source" => "required|string|" . Rule::in(array_keys(InvitationConstants::SOURCES)),
        ]);

        $emails = $this->parseEmails($data["emails"]);

        if ($data["source"] == InvitationConstants::SUPER_ADMIN) {
            return $this->sendSuperAdminInvite($request, $emails);
        }

        return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Invalid source selected");
    }

    function parseEmails($emails_string)
    {
        $emails = explode(",", $emails_string);
        foreach ($emails as $key => $value) {
            $emails[$key] = trim($value);
            if (empty($value)) {
                unset($emails[$key]);
            }
        }

        return $emails;
    }

    function sendSuperAdminInvite($request, $emails)
    {
        DB::beginTransaction();
        try {
            InvitationService::invite([
                "user_id" => auth()->id(),
                "invited_by" => auth()->id(),
                "role_id" => $request->role_id,
                "invite_expires_at" => now()->addMinutes(30),
                "notify_on_join" => $request->notify_on_join ?? 1,
                "source" => InvitationConstants::SUPER_ADMIN,
            ], $emails);

            DB::commit();
            return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, "Invitation sent successfully");
        } catch (ValidationException $th) {
            DB::rollBack();
            throw $th;
        } catch (InvalidRequestException $e) {
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, $e->getMessage());
        } catch (\Throwable $th) {
            DB::rollBack();
            // throw $th;
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    public function handleCallback($source, $uuid)
    {
        $invite = Invitation::where("uuid", $uuid)->with(["inviter"])->firstOrFail();
        return view("auth.invitation.show", [
            "invite" => $invite,
        ]);
    }

    public function response(Request $request, $source)
    {
        try {
            $request->validate([
                "invite_id" => "required|exists:invitations,id",
                "response" => "required|in:accept,decline"
            ]);

            $process = InvitationService::respondToInvite(
                $request->invite_id,
                $request->response
            );

            if ($process["invite"]->status == StatusConstants::ACTIVE) {
                // Log user in
                auth()->login($process["user"]);

                // Send verify email link
                // if (empty(auth()->user()->email_verified_at)) {
                //     auth()->user()->sendEmailVerificationNotification();
                // }

                // Flash and redirect user to dashboard
                session()->flash(NotificationConstants::SUCCESS_MSG, "You`re in...");
                return redirect()->route("admin.home");
            }

            return view("auth.invitation.error", [
                "title" => "You declined the invitation.",
                "message" => "We all have our right to make decisions. We are glad to have you around anytime."
            ]);
        } catch (InvalidRequestException $e) {
            if ($e->getCode() == 420) {
                $invite = Invitation::find($request->invite_id);
                return redirect()->route("web.admin.invite.complete-onboarding", $invite->id);
            }
            return view("auth.invitation.error", [
                "title" => "Oops...",
                "message" => $e->getMessage()
            ]);
        } catch (\Throwable $e) {
            return view("auth.invitation.error", [
                "title" => "Oops...",
                "message" => "Something went wrong while trying processing your request."
            ]);
        }
    }

    public function completeOnboarding(Request $request, $invite_id)
    {
        $invite = Invitation::find($invite_id);
        return view("auth.invitation.complete_onboarding", [
            "invite" => $invite,
        ]);
    }

    public function completeOnboardingSubmit(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validate([
                "name" => "required|string",
                "password" => "required|string",
                "invite_id" => "required|exists:invitations,id"
            ]);

            $invite = Invitation::find($request->invite_id);

            $user = (new RegistrationService)->create([
                "name" => $data["name"],
                "email" => $invite->invitee_email,
                "role" => UserConstants::ADMIN,
                "password" => $data["password"],
            ]);

            DB::commit();
            $request->request->add(["response" => "accept"]);

            return $this->response($request, $invite->source);
        } catch (ValidationException $th) {
            DB::rollBack();
            throw $th;
        } catch (InvalidRequestException $th) {
            DB::rollBack();
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            DB::rollBack();
            // throw $th;
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, $this->serverErrorMessage);
        }
    }
}
