<?php

namespace App\Http\Controllers\Admin\Business;

use App\Constants\General\AppConstants;
use App\Constants\General\NotificationConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Http\Controllers\Controller;
use App\Services\Business\IndustryService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Admin CRUD for the B2B signup industries (web §01). Server-rendered, mirroring
 * the FAQ-category admin pattern.
 */
class IndustryController extends Controller
{
    protected $industry_service;

    public function __construct()
    {
        $this->industry_service = new IndustryService;
    }

    public function index()
    {
        $industries = IndustryService::list()->paginate(AppConstants::ADMIN_PAGINATION_SIZE);

        return view("dashboards.admin.pages.business.industry.index", [
            "sn" => $industries->firstItem(),
            "industries" => $industries,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
        ]);
    }

    public function create()
    {
        return view("dashboards.admin.pages.business.industry.create", [
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
        ]);
    }

    public function store(Request $request)
    {
        try {
            $this->industry_service->store($request->all());

            return redirect()->route("admin.industries.index")
                ->with(NotificationConstants::SUCCESS_MSG, "Industry created successfully");
        } catch (ValidationException $th) {
            throw $th;
        } catch (\Throwable $th) {
            return redirect()->back()->withInput($request->all())
                ->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    public function edit($id)
    {
        $industry = $this->industry_service->getById($id);

        return view("dashboards.admin.pages.business.industry.create", [
            "industry" => $industry,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
        ]);
    }

    public function update(Request $request, $id)
    {
        try {
            $this->industry_service->update($request->all(), $id);

            return redirect()->route("admin.industries.index")
                ->with(NotificationConstants::SUCCESS_MSG, "Industry updated successfully");
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
            $this->industry_service->delete($id);

            return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, "Industry deleted successfully");
        } catch (ModelNotFoundException $th) {
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }
}
