<?php

namespace App\Http\Controllers\Admin\Member;

use App\Constants\General\AppConstants;
use App\Constants\General\NotificationConstants;
use App\Constants\General\StatusConstants;
use App\Constants\Invitation\InvitationConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Invitation;
use App\Services\Invitation\InvitationService;
use App\Services\Invitation\MemberService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Throwable;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        $admins = Admin::paginate(AppConstants::ADMIN_PAGINATION_SIZE);
        $roles = Role::paginate(AppConstants::ADMIN_PAGINATION_SIZE);
        $invitations = Invitation::source()->whereNotIn("status", [StatusConstants::ACTIVE])
            ->latest()
            ->get();
        return view("dashboards.admin.pages.authorization.members.index", [
            "sn" => $admins->firstItem(),
            "roles" => $roles,
            "admins" => $admins,
            "invitations" => $invitations,
        ]);
    }


    public function changeRole(Request $request)
    {
        try {
            MemberService::changeRole($request->all());
            return redirect()->back()
                ->with(NotificationConstants::SUCCESS_MSG, "Member role updated successfully.");
        } catch (ValidationException $e) {
            throw $e;
        } catch (InvalidRequestException $e) {
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, $e->getMessage());
        } catch (Throwable $e) {
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    public function deleteMember(Request $request, $member)
    {
        try {
            $data = $request->validate([
                "member_id" => "required|string",
                "confirmation" => "required|in:yes,no",
                "source" => "required|string|" . Rule::in(array_keys(InvitationConstants::SOURCES)),
            ]);

            MemberService::removeMember($data["member_id"], $data["source"], $data["confirmation"]);
            return redirect()->back()
                ->with(NotificationConstants::SUCCESS_MSG, "Member removed successfully.");
        } catch (ValidationException $e) {
            throw $e;
        } catch (InvalidRequestException $e) {
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, $e->getMessage());
        } catch (Throwable $e) {
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    public function invitationDestroy(Request $request, $invitation_id)
    {
        InvitationService::delete($invitation_id);
        return redirect()->back()
            ->with(NotificationConstants::SUCCESS_MSG, "Invitation deleted successfully.");
    }
}
