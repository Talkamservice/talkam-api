<?php

namespace App\Http\Controllers\Admin\Web;

use App\Constants\General\NotificationConstants;
use App\Constants\General\StatusConstants;
use App\Http\Controllers\Controller;
use App\Models\PrivacyPolicy;
use App\Services\PrivacyPolicy\PrivacyPolicyService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PrivacyPolicyController extends Controller
{
    protected $privacy_policy_service;

    public function __construct()
    {
        $this->privacy_policy_service = new PrivacyPolicyService;
    }

    public function create()
    {
        $privacy_policy = PrivacyPolicy::latest()->first();
        return view("dashboards.admin.pages.privacy_policy.create", [
            "privacy_policy" => $privacy_policy,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
        ]);
    }

    public function store(Request $request)
    {
        try {
            $this->privacy_policy_service->store($request->all());
            return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, "Privacy policy updated successfully");
        } catch (ValidationException $th) {
            throw $th;
        } catch (\Throwable $th) {
            // throw $th;
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }
}
