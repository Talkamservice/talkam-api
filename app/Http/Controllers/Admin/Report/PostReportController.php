<?php

namespace App\Http\Controllers\Admin\Report;

use App\Constants\General\AppConstants;
use App\Constants\General\NotificationConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Http\Controllers\Controller;
use App\Models\PostReport;
use App\Services\Report\Post\PostReportService;
use Illuminate\Http\Request;

class PostReportController extends Controller
{
    protected $post_report_service;

    public function __construct()
    {
        $this->post_report_service = new PostReportService;
    }

    public function reportList()
    {
        $post_report_lists = PostReport::latest()->paginate(AppConstants::ADMIN_PAGINATION_SIZE);
        return view('dashboards.admin.pages.report.post.index', [
            "sn" => $post_report_lists->firstItem(),
            'post_report_lists' => $post_report_lists,
        ]);
    }

    public function show($id)
    {
        $post_report = PostReport::findOrFail($id);
        return view('dashboards.admin.pages.report.post.show', [
            'post_report' => $post_report,
        ]);
    }

    public function updateStatus(Request $request, $post_report_id)
    {
        try {
            $this->post_report_service->changeStatus($request->all(), $post_report_id);
            return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, "Post report updated successfully");
        } catch (ModelNotFoundException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (InvalidRequestException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            // throw $th;
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    public function  deleteReport(Request $request, $post_report_id)
    {
        try {
            $this->post_report_service->delete($post_report_id);
            return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, "Post report deleted successfully");
        } catch (ModelNotFoundException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (InvalidRequestException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            // throw $th;
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

}
