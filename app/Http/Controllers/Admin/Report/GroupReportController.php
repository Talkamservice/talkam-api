<?php

namespace App\Http\Controllers\Admin\Report;

use App\Constants\General\AppConstants;
use App\Constants\General\NotificationConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Http\Controllers\Controller;
use App\Models\GroupMemberReport;
use App\Models\GroupReport;
use App\Services\Report\CustomService;
use App\Services\Report\Group\GroupReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class GroupReportController extends Controller
{
    protected $group_report_service;
    protected $custom_service;

    public function __construct()
    {
        $this->group_report_service = new GroupReportService;
        $this->custom_service = new CustomService;
    }

    public function reportList(Request $request)
    {
        $data = [
            'search' => $request->get('search'),
            'date' => $request->get('date')
        ];
        // Paginate reported group lists
        $group_report_lists = $this->custom_service->listGroupReports($data)
            ->latest()
            ->paginate(AppConstants::ADMIN_PAGINATION_SIZE);

        return view('dashboards.admin.pages.report.group.index', [
            "sn" => $group_report_lists->firstItem(),
            'group_report_lists' => $group_report_lists,
        ]);
    }

    public function groupReportList(Request $request)
    {
        $data = [
            'search' => $request->get('search'),
            'date' => $request->get('date')
        ];
        // Paginate reported group members
        $reported_members = $this->custom_service->listGroupMemberReports($data)
            ->latest()
            ->get()
            ->unique("group_id");

        return view('dashboards.admin.pages.report.group.member.index', [
            // 'sn' => $reported_members->firstItem(),
            'reported_members' => $reported_members,
        ]);
    }


    public function show($id)
    {
        $group_report = GroupReport::with('group.members')->findOrFail($id);
        $reasons_count = GroupReport::where('group_id', $group_report->group_id)->count();
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

    public function showGroupMemberReport($id)
    {
        // Retrieve the group member report with the related group member and user
        $group_member_report = GroupMemberReport::with('user')->findOrFail($id);

        // Count the number of reports for the group member
        $reasons_count = GroupMemberReport::where('group_member_id', $group_member_report->group_member_id)->count();

        // Get the list of reports for the group member, including the user who reported
        $group_member_report_lists = GroupMemberReport::where('group_member_id', $group_member_report->group_member_id)
            ->latest()
            ->with('user') // Load the user who reported
            ->paginate(AppConstants::ADMIN_PAGINATION_SIZE);

        // Pass data to the view
        return view('dashboards.admin.pages.report.group.member.show-report', [
            'group_member_report' => $group_member_report,
            'reasons_count' => $reasons_count,
            'group_member_report_lists' => $group_member_report_lists,
        ]);
    }



    public function suspendBanReportedGroup(Request $request, $group_id)
    {
        try {
            $message = $this->group_report_service->suspendOrBanGroup($request, $group_id);
            // Check if message is an error message or success
            $status = strpos($message, 'already') !== false ? NotificationConstants::ERROR_MSG : NotificationConstants::SUCCESS_MSG;
            return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, $message);
        } catch (ModelNotFoundException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (InvalidRequestException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            // throw $th;
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }
    public function activateReportedGroup(Request $request, $group_report_id)
    {
        try {
            $group_report = $this->group_report_service->getById($group_report_id);
            $message = $this->group_report_service->suspensionLift($group_report->group);
            return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, $message);
        } catch (ModelNotFoundException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (InvalidRequestException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            // throw $th;
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }
    public function deleteReportedGroup(Request $request, $group_report_id)
    {
        try {
            $message = $this->group_report_service->deleteGroup($group_report_id);
            return redirect()->route('admin.reports.group.lists')->with(NotificationConstants::SUCCESS_MSG, $message);
        } catch (ModelNotFoundException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (InvalidRequestException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            throw $th;
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    public function undoGroupMemberSuspension(Request $request, $group_member_id)
    {
        try {
            // Ensure the group_report_service method is properly defined
            $message = $this->group_report_service->undoGroupMemberSuspension($group_member_id);
            return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, $message);
        } catch (ModelNotFoundException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (InvalidRequestException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }


    public function suspendBanReportedGroupMember(Request $request, $group_member_report_id)
    {
        try {
            // Apply suspension logic (update the group member's status)
            $message = $this->group_report_service->suspendOrBanMember($request, $group_member_report_id);
            $status = strpos($message, 'already') !== false ? NotificationConstants::ERROR_MSG : NotificationConstants::SUCCESS_MSG;
            return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, $message);
        } catch (ModelNotFoundException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, 'Group member report or member not found.');
        } catch (InvalidRequestException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            // throw $th;
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, 'Something went wrong while trying to process your request.');
        }
    }
}
