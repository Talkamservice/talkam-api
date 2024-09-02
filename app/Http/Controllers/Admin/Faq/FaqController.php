<?php

namespace App\Http\Controllers\Admin\Faq;

use App\Constants\General\AppConstants;
use App\Constants\General\NotificationConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\WellnessCourse;
use App\Services\Faq\FaqService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FaqController extends Controller
{
    protected $faq_service;

    public function __construct()
    {
        $this->faq_service = new FaqService;
    }

    public function index()
    {
        $faqs = $this->faq_service->list()->paginate(AppConstants::ADMIN_PAGINATION_SIZE);
        return view("dashboards.admin.pages.faq.index", [
            "sn" => $faqs->firstItem(),   
            "faqs" => $faqs,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS
        ]);
    }

    public function create()
    {
        return view("dashboards.admin.pages.faq.create", [
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
        ]);
    }

    public function store(Request $request)
    {
        try {
            $this->faq_service->store($request->all());
            return redirect()->route("admin.faqs.index")->with(NotificationConstants::SUCCESS_MSG, "Faq created successfully");
        } catch (ValidationException $th) {
            throw $th;
        } catch (\Throwable $th) {
            // throw $th;
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    public function edit($faq_id)
    {
        $faq = $this->faq_service->getById($faq_id);;
        return view("dashboards.admin.pages.faq.create", [
            "faq" => $faq,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
        ]);
    }

    public function update(Request $request, Faq $faq)
    {
        try {
            $this->faq_service->update($request->all(), $faq->id);
            return redirect()->route("admin.faqs.index")->with(NotificationConstants::SUCCESS_MSG, "Faq updated successfully");
        } catch (ValidationException $th) {
            throw $th;
        } catch (ModelNotFoundException $th) {
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            // throw $th;
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    public function destroy(Request $request, $faq_id)
    {
        try {
            $this->faq_service->delete($faq_id);
            return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, "Faq deleted successfully");
        } catch (ModelNotFoundException $th) {
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            // throw $th;
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }
}
