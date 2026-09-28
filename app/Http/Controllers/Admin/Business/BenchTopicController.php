<?php

namespace App\Http\Controllers\Admin\Business;

use App\Constants\General\AppConstants;
use App\Constants\General\NotificationConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Http\Controllers\Controller;
use App\Services\Business\BenchTopicService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Admin CRUD for the therapist-bench specialties (web §4b). Server-rendered,
 * mirroring the industry admin pattern.
 */
class BenchTopicController extends Controller
{
    protected $bench_topic_service;

    public function __construct()
    {
        $this->bench_topic_service = new BenchTopicService;
    }

    public function index()
    {
        $topics = BenchTopicService::list()->paginate(AppConstants::ADMIN_PAGINATION_SIZE);

        return view("dashboards.admin.pages.business.bench-topic.index", [
            "sn" => $topics->firstItem(),
            "topics" => $topics,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
        ]);
    }

    public function create()
    {
        return view("dashboards.admin.pages.business.bench-topic.create", [
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
        ]);
    }

    public function store(Request $request)
    {
        try {
            $this->bench_topic_service->store($request->all());

            return redirect()->route("admin.bench-topics.index")
                ->with(NotificationConstants::SUCCESS_MSG, "Bench topic created successfully");
        } catch (ValidationException $th) {
            throw $th;
        } catch (\Throwable $th) {
            return redirect()->back()->withInput($request->all())
                ->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    public function edit($id)
    {
        $topic = $this->bench_topic_service->getById($id);

        return view("dashboards.admin.pages.business.bench-topic.create", [
            "topic" => $topic,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
        ]);
    }

    public function update(Request $request, $id)
    {
        try {
            $this->bench_topic_service->update($request->all(), $id);

            return redirect()->route("admin.bench-topics.index")
                ->with(NotificationConstants::SUCCESS_MSG, "Bench topic updated successfully");
        } catch (ValidationException $th) {
            throw $th;
        } catch (ModelNotFoundException $th) {
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            return redirect()->back()->withInput($request->all())
                ->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    public function destroy($id)
    {
        try {
            $this->bench_topic_service->delete($id);

            return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, "Removed from the bench (the specialty itself is kept for therapists and the app)");
        } catch (ModelNotFoundException $th) {
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }
}
