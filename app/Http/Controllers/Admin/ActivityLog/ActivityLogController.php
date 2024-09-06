<?php

namespace App\Http\Controllers\Admin\ActivityLog;

use App\Constants\General\AppConstants;
use App\Constants\General\StatusConstants;
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
}
