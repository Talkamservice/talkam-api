<?php

namespace App\Http\Controllers\Admin\ActivityLog;

use App\Constants\General\AppConstants;
use App\Constants\General\NotificationConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Http\Controllers\Controller;
use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{

    protected $activity_log_service;

    public function __construct(ActivityLogService $activity_log_service)
    {
        $this->activity_log_service = $activity_log_service;
    }

    public function index(Request $request)
    {
        $activity_logs = $this->activity_log_service->list($request->all()) ->with(['admin.user'])->paginate(AppConstants::ADMIN_PAGINATION_SIZE);
        return view('dashboards.admin.pages.activity-log.index', [
            "sn" => $activity_logs->firstItem(),
            "activity_logs" => $activity_logs,
            "Active" => StatusConstants::ACTIVE,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $this->activity_log_service->delete($id);
            return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, "Activity log deleted successfully");
        } catch (ModelNotFoundException $th) {
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (InvalidRequestException $th) {
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            throw $th;
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while processing your request.");
        }
    }
}
