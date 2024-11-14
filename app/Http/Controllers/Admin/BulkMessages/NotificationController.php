<?php

namespace App\Http\Controllers\Admin\BulkMessages;

use App\Constants\General\AppConstants;
use App\Constants\General\NotificationConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BulkMessages\NotificationService;
use Dotenv\Exception\ValidationException;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    protected $bulk_notifications_service;

    public function __construct(NotificationService $bulk_notifications_service)
    {
        $this->bulk_notifications_service = $bulk_notifications_service;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $notifications = $this->bulk_notifications_service->list($request->all())->paginate(AppConstants::ADMIN_PAGINATION_SIZE);
        return view('dashboards.admin.pages.bulk-messages.notification.index', [
            "sn" => $notifications->firstItem(),
            "notifications" => $notifications,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
            'Sent' => StatusConstants::SENT,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('dashboards.admin.pages.bulk-messages.notification.create', [
            'users' => User::where('status', StatusConstants::ACTIVE)->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $this->bulk_notifications_service->send($request->all());
            return redirect()->route('admin.notifications.send-bulk-notification.index')->with(NotificationConstants::SUCCESS_MSG, "Notification created successfully");
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
        // Implement show method if needed
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        return view('dashboards.admin.pages.bulk-messages.notification.create', [
            'notification' => $this->bulk_notifications_service->getById($id),
            'users' => User::where('status', StatusConstants::ACTIVE)->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $this->bulk_notifications_service->send($request->all(), $id);
            return redirect()->route('admin.notifications.send-bulk-notification.index')->with(NotificationConstants::SUCCESS_MSG, "Notification updated successfully");
        } catch (ValidationException $th) {
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
            $this->bulk_notifications_service->delete($id);
            return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, "Notification deleted successfully");
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
            $this->bulk_notifications_service->changeStatus($request, $id);
            return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, "Notification status updated successfully");
        } catch (ModelNotFoundException $th) {
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (InvalidRequestException $th) {
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while processing your request.");
        }
    }
}
