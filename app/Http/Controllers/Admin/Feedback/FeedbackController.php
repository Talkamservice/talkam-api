<?php

namespace App\Http\Controllers\Admin\Feedback;

use App\Constants\General\AppConstants;
use App\Constants\General\NotificationConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Services\Feedback\FeedbackService;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    protected $feedback_service;

    public function __construct()
    {
        $this->feedback_service = new FeedbackService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $data = [
            'search' => $request->get('search'),
        ];
        $feedbacks = $this->feedback_service->list($data)->latest()->paginate(AppConstants::ADMIN_PAGINATION_SIZE);
        return view('dashboards.admin.pages.feedback.index', [
            "feedbacks" => $feedbacks,
            "boolOptions" => AppConstants::BOOL_OPTIONS,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('dashboards.admin.pages.feedbacks.create', [
            "boolOptions" => AppConstants::BOOLEAN_OPTIONS,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // try {
        //     $this->feedback_service->create($request->all());
        //     return redirect()->route("admin.emergency-contacts.index")->with(NotificationConstants::SUCCESS_MSG, "Emergency contact created successfully.");
        // } catch (ValidationException $th) {
        //     throw $th;
        // } catch (\Throwable $th) {
        //     // throw $th;
        //     return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        // }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        // $feedback = $this->feedback_service->getById($id);
        // return view("dashboards.admin.pages.feedbacks.create", [
        //     "feedback" => $feedback,
        //     "boolOptions" => AppConstants::BOOLEAN_OPTIONS,
        //     "statusOptions" => StatusConstants::ACTIVE_OPTIONS
        // ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // try {
        //     $this->feedback_service->update($request->all(), $id);
        //     return redirect()->route("admin.emergency-contacts.index")->with(NotificationConstants::SUCCESS_MSG, "Emergency contact updated successfully.");
        // } catch (ValidationException $th) {
        //     throw $th;
        // } catch (ModelNotFoundException $th) {
        //     return redirect()->back()->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        // } catch (\Throwable $th) {
        //     return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        // }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $this->feedback_service->delete($id);
            return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, "Feedback deleted successfully.");
        } catch (ModelNotFoundException $th) {
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            // throw $th;
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    public function resolveFeedback(Request $request, $comment_report_id)
    {
        try {
            $this->feedback_service->changeStatus($request->all(), $comment_report_id);
            return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, "feedback resolved successfully");
        } catch (ModelNotFoundException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (InvalidRequestException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            // throw $th;
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    public function respondFeedback(Request $request, $feedback_id)
    {
        try {
            $this->feedback_service->respond($request, $feedback_id);
            return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, "message sent successfully");
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
