<?php

namespace App\Http\Controllers\Admin\Report;

use App\Constants\General\AppConstants;
use App\Constants\General\NotificationConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Post\PostPollResource;
use App\Models\PostReport;
use App\Services\Report\CustomService;
use App\Services\Report\Post\PostReportService;
use Illuminate\Http\Request;

class PostReportController extends Controller
{
    protected $post_report_service;
    protected $custom_service;

    public function __construct()
    {
        $this->post_report_service = new PostReportService;
        $this->custom_service = new CustomService;
    }

    public function reportList(Request $request)
    {
        $data = [
            'search' => $request->get('search'),
            'date' => $request->get('date')
        ];
        $post_report_lists = $this->custom_service->listPostReports($data)->latest()
            ->paginate(AppConstants::ADMIN_PAGINATION_SIZE);
        // dd(  $post_report_lists);
        return view('dashboards.admin.pages.report.post.index', [
            "sn" => $post_report_lists->firstItem(),
            'post_report_lists' => $post_report_lists,
        ]);
    }

    public function show($id)
    {
        // Fetch the specific post report with associated post and polls
        $post_report = PostReport::with('post.polls')->find($id);

        // Initialize polls as null
        $polls = null;

        // Check if the post type is Poll and fetch poll details if applicable
        if ($post_report->post && $post_report->post->type == 'Poll') {
            $polls = PostPollResource::collection($post_report->post->polls);
        }

        // Count the number of reports for the specific post
        $reasons_count = PostReport::where('post_id', $post_report->post_id)->count();

        // Fetch all reports related to the specific post for pagination
        $post_report_lists = PostReport::where('post_id', $post_report->post_id)
            ->latest()
            ->with('user')
            ->paginate(AppConstants::ADMIN_PAGINATION_SIZE);

        // Return the view with data
        return view('dashboards.admin.pages.report.post.show', [
            'post_report' => $post_report,
            'polls' => $polls,
            'reasons_count' => $reasons_count,
            'post_report_lists' => $post_report_lists,
        ]);
    }


    public function updateStatus(Request $request, $post_report_id)
    {
        try {
            $this->post_report_service->changeStatus($request->all(), $post_report_id);
            return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, "Post report status updated successfully");
        } catch (ModelNotFoundException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (InvalidRequestException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            throw $th;
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    public function deleteReportedPost(Request $request, $post_report_id)
    {
        try {
            $this->post_report_service->delete($post_report_id);
            dd('"Post report deleted successfully. Redirecting...');
            return redirect()->route('admin.reports.post.lists')->with(NotificationConstants::SUCCESS_MSG, "Reported post deleted successfully");
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
