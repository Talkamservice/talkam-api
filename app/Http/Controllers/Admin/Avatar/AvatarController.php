<?php

namespace App\Http\Controllers\Admin\Avatar;

use App\Constants\General\AppConstants;
use App\Constants\General\NotificationConstants;
use App\Constants\General\StatusConstants;
use App\Http\Controllers\Controller;
use App\Models\Avatar;
use App\Services\User\AvatarService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AvatarController extends Controller
{
    protected $avatar_service;

    public function __construct()
    {
        $this->avatar_service = new AvatarService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $avatars = $this->avatar_service->list()->paginate(AppConstants::ADMIN_PAGINATION_SIZE);
        return view('dashboards.admin.pages.avatars.index', [
            "avatars" => $avatars,
            "boolOptions" => AppConstants::BOOL_OPTIONS,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('dashboards.admin.pages.avatars.create', [
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $this->avatar_service->create($request->all());
            return redirect()->route("admin.avatars.index")->with(NotificationConstants::SUCCESS_MSG, "Avatar created successfully.");
        } catch (ValidationException $th) {
            throw $th;
        } catch (\Throwable $th) {
            // throw $th;
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $avatar = Avatar::findOrFail($id);
        return view("dashboards.admin.pages.avatars.create", [
            "avatar" => $avatar,
            "boolOptions" => AppConstants::BOOLEAN_OPTIONS,
            "statusOptions" => StatusConstants::ACTIVE_OPTIONS
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $this->avatar_service->updateCrud($request->all(), $id);
            return redirect()->route("admin.avatars.index")->with(NotificationConstants::SUCCESS_MSG, "Avatar updated successfully.");
        } catch (ValidationException $th) {
            throw $th;
        } catch (\Throwable $th) {
            //throw $th;
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $avatar = $this->avatar_service->getById($id);
            $avatar->delete();
            return redirect()->route("admin.avatars.index")->with(NotificationConstants::SUCCESS_MSG, "Avatar deleted successfully.");
        } catch (\Throwable $th) {
            //throw $th;
            return redirect()->back()->with(NotificationConstants::ERROR_MSG, "Something went wrong while trying to process your request.");
        }
    }
}
