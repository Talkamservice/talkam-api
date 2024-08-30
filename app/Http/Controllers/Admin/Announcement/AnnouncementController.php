<?php

namespace App\Http\Controllers\Admin\Announcement;

use App\Constants\General\AppConstants;
use App\Constants\General\NotificationConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\Announcement\AnnouncementService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AnnouncementController extends Controller
{
    protected $announcement_service;

    public function __construct(AnnouncementService $announcement_service)
    {
        $this->announcement_service = $announcement_service;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $announcements = $this->announcement_service->list($request->all())->paginate(AppConstants::ADMIN_PAGINATION_SIZE);
        return view('dashboards.admin.pages.announcement.index', [
            "sn" => $announcements->firstItem(),
            "announcements" => $announcements,
            "Active" => StatusConstants::ACTIVE,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('dashboards.admin.pages.announcement.create', [
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $this->announcement_service->send($request->all());
            return redirect()->route('admin.announcements.index')->with(NotificationConstants::SUCCESS_MSG, "Announcement created successfully");
        } catch (ValidationException $th) {
            throw $th;
        } catch (\Throwable $th) {
            // throw $th;
            return redirect()->back()->withInput()->with(NotificationConstants::ERROR_MSG, "Something went wrong while processing your request.");
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        return view('dashboards.admin.pages.announcement.create', [
            'announcement' => $this->announcement_service->getById($id),
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $this->announcement_service->send($request->all(), $id);
            return redirect()->route('admin.announcements.index')->with(NotificationConstants::SUCCESS_MSG, "Announcement updated successfully");
        } catch (ValidationException $th) {
            throw $th;
        } catch (ModelNotFoundException $th) {
            return redirect()->back()->withInput()->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (InvalidRequestException $th) {
            return redirect()->back()->withInput()->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            // throw $th;
            return redirect()->back()->withInput()->with(NotificationConstants::ERROR_MSG, "Something went wrong while processing your request.");
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $this->announcement_service->delete($id);
            return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, "Announcement deleted successfully");
        } catch (ModelNotFoundException $th) {
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (InvalidRequestException $th) {
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while processing your request.");
        }
    }

    public function changeStatus(Request $request, string $id)
    {
        // dd($request->all());
        try {
            $this->announcement_service->changeStatus($request, $id);
            return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, "Announcement status updated successfully");
        } catch (ModelNotFoundException $th) {
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (InvalidRequestException $th) {
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while processing your request.");
        }
    }
}
