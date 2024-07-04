<?php

namespace App\Http\Controllers\Admin\Post;

use App\Constants\General\AppConstants;
use App\Constants\General\NotificationConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Http\Controllers\Controller;
use App\Services\PostCategory\PostCategoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PostCategoryController extends Controller
{
    protected $post_category_service;

    public function __construct()
    {
        $this->post_category_service = new PostCategoryService;
    }

    public function index(Request $request)
    {
        $categories = $this->post_category_service->list($request->all())->whereNull("category_id")->paginate(AppConstants::ADMIN_PAGINATION_SIZE);
        return view("dashboards.admin.pages.content.categories.index", [
            "sn" => $categories->firstItem(),
            "categories" => $categories,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS
        ]);
    }

    public function create()
    {
        return view("dashboards.admin.pages.content.categories.create", [
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
        ]);
    }

    public function store(Request $request)
    {
        try {
            $this->post_category_service->create($request->all());
            return redirect()->route("admin.post-categories.index")->with(NotificationConstants::SUCCESS_MSG, "Category created successfully");
        } catch (ValidationException $th) {
            throw $th;
        } catch (\Throwable $th) {
            // throw $th;
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    public function edit($category_id)
    {
        $category = $this->post_category_service->getById($category_id);
        return view("dashboards.admin.pages.content.categories.create", [
            "category" => $category,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
        ]);
    }

    public function update(Request $request, $category_id)
    {
        try {
            $this->post_category_service->update($request->all(), $category_id);
            return redirect()->route("admin.post-categories.index")->with(NotificationConstants::SUCCESS_MSG, "Category updated successfully");
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

    public function destroy(Request $request, $category_id)
    {
        try {
            $this->post_category_service->delete($category_id);
            return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, "Category deleted successfully");
        } catch (ModelNotFoundException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (InvalidRequestException $th) {
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            // throw $th;
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    public function subCategories(Request $request, $category_id)
    {
        $category = $this->post_category_service->getById($category_id);
        $categories = $this->post_category_service->list($request->all())->where("category_id", $category_id)->paginate(AppConstants::ADMIN_PAGINATION_SIZE);
        return view("dashboards.admin.pages.content.categories.sub_index", [
            "sn" => $categories->firstItem(),
            "category" => $category,
            "categories" => $categories,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS
        ]);
    }

    public function createCategory(Request $request, $category_id)
    {
        $category = $this->post_category_service->getById($category_id);
        return view("dashboards.admin.pages.content.categories.sub_create", [
            "category" => $category,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS
        ]);
    }

    public function saveSubCategory(Request $request, $category_id)
    {
        try {
            $this->post_category_service->create($request->all());
            return redirect()->route("admin.categories.sub-categories.index", $category_id)->with(NotificationConstants::SUCCESS_MSG, "Category created successfully");
        } catch (ValidationException $th) {
            throw $th;
        } catch (\Throwable $th) {
            // throw $th;
            return redirect()->back()->withInput($request->all())->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    public function editSubCategory($category_id, $subcategory_id)
    {
        $category = $this->post_category_service->getById($category_id);
        $sub_category = $this->post_category_service->getById($subcategory_id);
        return view("dashboards.admin.pages.content.categories.sub_create", [
            "category" => $category,
            "sub_category" => $sub_category,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS,
        ]);
    }

    public function updateSubCategory(Request $request, $category_id, $subcategory_id)
    {
        try {
            $this->post_category_service->update($request->all(), $subcategory_id);
            return redirect()->route("admin.categories.sub-categories.index", $category_id)->with(NotificationConstants::SUCCESS_MSG, "Category updated successfully");
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

    public function deleteSubCategory(Request $request, $category_id, $subcategory_id)
    {
        try {
            $this->post_category_service->delete($subcategory_id);
            return redirect()->back()->with(NotificationConstants::SUCCESS_MSG, "Category deleted successfully");
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
