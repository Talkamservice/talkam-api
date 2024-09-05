<?php

namespace App\Http\Controllers\Admin\Faq;

use App\Constants\General\AppConstants;
use App\Constants\General\NotificationConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\ModelNotFoundException;
use App\Http\Controllers\Controller;
use App\Models\FaqCategory;
use App\Services\Faq\FaqCategoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FaqCategoryController extends Controller
{

    protected $faq_category_service;

    public function __construct()
    {
        $this->faq_category_service = new FaqCategoryService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $faq_categories = $this->faq_category_service->list()->paginate(AppConstants::ADMIN_PAGINATION_SIZE);
        return view("dashboards.admin.pages.faq.category.index", [
            "sn" => $faq_categories->firstItem(),   
            "faq_categories" => $faq_categories,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view("dashboards.admin.pages.faq.category.create", [
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $this->faq_category_service->store($request->all());
            return redirect()->route("admin.faqs.index")->with(NotificationConstants::SUCCESS_MSG, "Faq Category created successfully");
        } catch (ValidationException $th) {
            throw $th;
        } catch (\Throwable $th) {
            throw $th;
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
       
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($faq_id)
    {
        $faq_category = $this->faq_category_service->getById($faq_id);;
        return view("dashboards.admin.pages.faq.category.create", [
            "faq_category" => $faq_category,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, FaqCategory $faq_category)
    {
        try {
            $this->faq_category_service->update($request->all(), $faq_category->id);
            return redirect()->route("admin.faqs.index")->with(NotificationConstants::SUCCESS_MSG, "Faq Category updated successfully");
        } catch (ValidationException $th) {
            throw $th;
        } catch (ModelNotFoundException $th) {
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            // throw $th;
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($faq_id)
    {
        try {
            $this->faq_category_service->delete($faq_id);
            return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, "Faq Category deleted successfully");
        } catch (ModelNotFoundException $th) {
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            // throw $th;
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }
}
