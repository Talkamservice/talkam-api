<?php

namespace App\Http\Controllers\Admin\Guideline;

use App\Constants\General\AppConstants;
use App\Constants\General\NotificationConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Http\Controllers\Controller;
use App\Services\Guideline\GuidelineService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GuidelineController extends Controller
{

    protected $guideline_service;

    public function __construct()
    {
        $this->guideline_service = new GuidelineService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $guidelines = $this->guideline_service->list($request->all())->paginate(AppConstants::ADMIN_PAGINATION_SIZE);
        return view("dashboards.admin.pages.guideline.index", [
            "sn" => $guidelines->firstItem(),
            "guidelines" => $guidelines,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view("dashboards.admin.pages.guideline.create", [
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $this->guideline_service->create($request->all());
            return redirect()->route("admin.guidelines.index")->with(NotificationConstants::SUCCESS_MSG, "Guideline created successfully");
        } catch (ValidationException $th) {
            throw $th;
        } catch (\Throwable $th) {
            // throw $th;
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($guideline_id)    {
        $guideline = $this->guideline_service->getById($guideline_id);
        return view("dashboards.admin.pages.guideline.create", [
            "guideline" => $guideline,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $guideline_id)
    {
        try {
            $this->guideline_service->update($request->all(), $guideline_id);
            return redirect()->route("admin.guidelines.index")->with(NotificationConstants::SUCCESS_MSG, "Guideline updated successfully");
        } catch (ValidationException $th) {
            throw $th;
        } catch (ModelNotFoundException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (InvalidRequestException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            // throw $th;
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $guideline_id)
    {
        try {
            $this->guideline_service->delete($guideline_id);
            return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, "Guideline deleted successfully");
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
