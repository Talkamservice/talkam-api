<?php

namespace App\Http\Controllers\Admin\Report;

use App\Constants\General\AppConstants;
use App\Http\Controllers\Controller;
use App\Models\GroupReport;
use Illuminate\Http\Request;

class GroupReportController extends Controller
{
    protected $group_report_service;

    public function __construct()
    {
        $this->group_report_service = new GroupReport;
    }

    public function reportList()
    {
        $group_report_lists = GroupReport::with(['group', 'user'])->latest()
            ->paginate(AppConstants::ADMIN_PAGINATION_SIZE);
        return view('dashboards.admin.pages.report.group.index', [
            "sn" => $group_report_lists->firstItem(),
            'group_report_lists' => $group_report_lists,
        ]);
    }

    public function show($id)
    {
        // Fetch the specific group report with associated group
        $group_report = GroupReport::with('group')->findOrFail($id);
        // Count the number of reports for the specific group
        $reasons_count = GroupReport::where('group_id', $group_report->group_id)->count();

        // Fetch all reports related to the specific group for pagination
        $group_report_lists = GroupReport::where('group_id', $group_report->group_id)
            ->latest()
            ->with('user')
            ->paginate(AppConstants::ADMIN_PAGINATION_SIZE);

        return view('dashboards.admin.pages.report.group.show', [
            'group_report' => $group_report,
            'reasons_count' => $reasons_count,
            'group_report_lists' => $group_report_lists,
        ]);
    }
}
