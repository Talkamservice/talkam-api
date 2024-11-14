<?php

namespace App\Http\Controllers\Admin\User;

use App\Constants\General\NotificationConstants;
use App\Constants\General\StatusConstants;
use App\Http\Controllers\Controller;
use App\Models\AccountDeactivation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountStatusController extends Controller
{
   public function deactivationRequestLists(Request $request)
   {
      $builder = AccountDeactivation::with("user");

      // if (!empty($key = $request->search)) {
      //    $builder = $builder->search($key);
      // }

      $deactivation_requests = $builder->latest()->paginate();
      return view("dashboards.admin.pages.account_deactivation.index", [
         "sn" => $deactivation_requests->firstItem(),
         "deactivation_requests" => $deactivation_requests
      ]);
   }

   public function submitDeactivationRequest(Request $request)
   {
      DB::beginTransaction();
      try {
         $data = $request->validate([
            "deactivation_request_id" => "required|exists:account_deactivations,id",
         ]);

         $deactivation_request = AccountDeactivation::with(["user"])->find($data["deactivation_request_id"]);

         $user = $deactivation_request->user;

         if (in_array($deactivation_request->status, [StatusConstants::PENDING, StatusConstants::PROCESSING])) {
            $deactivation_request->update([
               "status" => StatusConstants::DISABLED,
            ]);

            $user->update([
               "status" => StatusConstants::DISABLED
            ]);
         } else {
            $deactivation_request->update([
               "status" => StatusConstants::UNRESOLVED,
            ]);

            $user->update([
               "status" => StatusConstants::ACTIVE,
            ]);
         }

         DB::commit();
         return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, "Account status updated sucessfully");
      } catch (\Throwable $th) {
         DB::rollBack();
         return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, "An error occured");
      }
   }
}
