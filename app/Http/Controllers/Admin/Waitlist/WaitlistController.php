<?php

namespace App\Http\Controllers\Admin\Waitlist;

use App\Constants\General\AppConstants;
use App\Constants\General\NotificationConstants;
use App\Constants\General\StatusConstants;
use App\Http\Controllers\Controller;
use App\Models\Waitlist;
use App\Services\Waitlist\WaitlistService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WaitlistController extends Controller
{
    protected $waitlist_service_service;

    public function __construct()
    {
        $this->waitlist_service_service = new WaitlistService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $waitlist_services = Waitlist::latest()->paginate();
        return view('dashboards.admin.pages.waitlist_services.index', [
            "waitlist_services" => $waitlist_services,
            "boolOptions" => AppConstants::BOOL_OPTIONS,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('dashboards.admin.pages.waitlist_services.create', [
            "boolOptions" => AppConstants::BOOLEAN_OPTIONS,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $this->waitlist_service_service->create($request->all());
            return redirect()->route("admin.emergency-contacts.index")->with(NotificationConstants::SUCCESS_MSG, "Emergency contact created successfully.");
        } catch (ValidationException $th) {
            throw $th;
        } catch (\Throwable $th) {
            // throw $th;
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $waitlist_service = Waitlist::findOrFail($id);
        return view("dashboards.admin.pages.waitlist_services.create", [
            "waitlist_service" => $waitlist_service,
            "boolOptions" => AppConstants::BOOLEAN_OPTIONS,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $this->waitlist_service_service->update($request->all(), $id);
            return redirect()->route("admin.emergency-contacts.index")->with(NotificationConstants::SUCCESS_MSG, "Emergency contact updated successfully.");
        } catch (ValidationException $th) {
            throw $th;
        } catch (\Throwable $th) {
            //throw $th;
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $waitlist_service = $this->waitlist_service_service->getById($id);
            $waitlist_service->delete();
            return redirect()->route("admin.emergency-contacts.index")->with(NotificationConstants::SUCCESS_MSG, "Emergency contact deleted successfully.");
        } catch (\Throwable $th) {
            //throw $th;
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }
}
