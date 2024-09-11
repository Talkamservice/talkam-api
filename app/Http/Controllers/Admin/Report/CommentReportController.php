<?php

namespace App\Http\Controllers\Admin\Report;

use App\Constants\General\AppConstants;
use App\Constants\General\NotificationConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Http\Controllers\Controller;
use App\Models\CommentReport;
use App\Services\Report\CustomService;
use App\Services\Report\Post\CommentReportService;
use Illuminate\Http\Request;

class CommentReportController extends Controller
{
    protected $comment_report_service;
    protected $custom_service;
    public function __construct()
    {
        $this->comment_report_service = new CommentReportService;
        $this->custom_service = new CustomService;
    }

    public function reportList(Request $request)
    {
       
        $data = [
            'search' => $request->get('search'),
            'date' => $request->get('date')
        ];
        $comment_report_lists = $this->custom_service->listCommentReports($data)->latest()->paginate(AppConstants::ADMIN_PAGINATION_SIZE);
        return view('dashboards.admin.pages.report.comment.index', [
            "sn" => $comment_report_lists->firstItem(),
            'comment_report_lists' => $comment_report_lists,
        ]);
    }


    public function show($id)
    {
        // Fetch the specific comment report
        $comment_report = CommentReport::with('comment')->find($id);
    
        if (!$comment_report || !$comment_report->comment) {
            // If the comment report or its comment is not found, redirect to a suitable page with a message
            return redirect()->route('admin.reports.post.lists')->with('error', 'Comment Report or associated comment not found.');
        }
    
        // Count the number of reports for the specific comment
        $reasons_count = CommentReport::where('comment_id', $comment_report->comment_id)->count();
    
        // Fetch all reports related to the specific comment for pagination
        $comment_report_lists = CommentReport::where('comment_id', $comment_report->comment_id)
            ->latest()
            ->with('user')
            ->paginate(AppConstants::ADMIN_PAGINATION_SIZE);
    
        return view('dashboards.admin.pages.report.comment.show', [
            'comment_report' => $comment_report,
            'reasons_count' => $reasons_count,
            'comment_report_lists' => $comment_report_lists,
        ]);
    }
    

    public function updateStatus(Request $request, $comment_report_id)
    {
        try {
            $this->comment_report_service->changeStatus($request->all(), $comment_report_id);
            return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, "comment report status updated successfully");
        } catch (ModelNotFoundException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (InvalidRequestException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            throw $th;
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    public function  deleteReportedComment(Request $request, $comment_report_id)
    {
        try {
            $this->comment_report_service->delete($comment_report_id);
            return redirect()->route('admin.reports.comment.lists')->with(NotificationConstants::SUCCESS_MSG, " Reported comment deleted successfully");
        } catch (ModelNotFoundException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (InvalidRequestException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            throw $th;
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }
}
