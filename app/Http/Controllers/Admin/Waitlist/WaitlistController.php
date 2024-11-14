<?php

namespace App\Http\Controllers\Admin\Waitlist;

use App\Constants\General\AppConstants;
use App\Constants\General\NotificationConstants;
use App\Constants\General\StatusConstants;
use App\Exports\WaitlistExport;
use App\Http\Controllers\Controller;
use App\Models\Waitlist;
use App\Services\Waitlist\WaitlistService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class WaitlistController extends Controller
{
    protected $waitlist_service;

    public function __construct()
    {
        $this->waitlist_service = new WaitlistService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $waitlists = Waitlist::latest()->paginate(AppConstants::ADMIN_PAGINATION_SIZE);
        return view('dashboards.admin.pages.waitlist.index', [
            "waitlists" => $waitlists,
            "boolOptions" => AppConstants::BOOL_OPTIONS,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
      //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
      //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $waitlist_service = $this->waitlist_service->getById($id);
            $waitlist_service->delete();
            return redirect()->route("admin.waitlists.index")->with(NotificationConstants::SUCCESS_MSG, "Waitlist deleted successfully.");
        } catch (\Throwable $th) {
            //throw $th;
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    public function export()
    {
        try {
            return Excel::download(new WaitlistExport, 'waitlists.xlsx');
            return redirect()->route("admin.waitlists.index")->with(NotificationConstants::SUCCESS_MSG, "Waitlists exported successfully.");
        } catch (\Throwable $th) {
            //throw $th;
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }
}
