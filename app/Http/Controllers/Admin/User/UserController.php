<?php

namespace App\Http\Controllers\Admin\User;

use App\Constants\Account\User\UserConstants;
use App\Constants\General\AppConstants;
use App\Constants\General\NotificationConstants;
use App\Constants\General\StatusConstants;
use App\Exceptions\General\InvalidRequestException;
use App\Exceptions\General\ModelNotFoundException;
use App\Http\Controllers\Controller;
use App\Models\Avatar;
use App\Models\User;
use App\QueryBuilders\User\UserQueryBuilder;
use App\Services\User\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    protected $user_service;
    public function __construct()
    {
        $this->user_service = new UserService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize(slugPermission("read user"));
        $users = UserQueryBuilder::filterList($request)->where("role", UserConstants::USER)->latest()
            ->paginate(AppConstants::ADMIN_PAGINATION_SIZE)
            ->appends($request->query());
        return view("dashboards.admin.pages.user.index", [
            "users" => $users,
            "statuses" => StatusConstants::ACTIVE_OPTIONS
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::findOrFail($id);
        $avatars = Avatar::status()->get();
        return view("dashboards.admin.pages.user.show", [
            "user" => $user,
            "avatars" => $avatars,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $this->user_service->update($request->all(), $id);
            return back()->with(NotificationConstants::SUCCESS_MSG, "User updated successfully");
        } catch (ValidationException $th) {
            throw $th;
        } catch (ModelNotFoundException $th) {
            return back()
                ->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            return back()->withInput($request->all())
                ->with(NotificationConstants::ERROR_MSG, $this->serverErrorMessage);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $this->user_service->delete($id);
            return back()->with(NotificationConstants::SUCCESS_MSG, "User deleted successfully");
        } catch (ModelNotFoundException $th) {
            return back()
                ->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            return back()
                ->with(NotificationConstants::ERROR_MSG, $this->serverErrorMessage);
        }
    }


    public function suspend(Request $request, string $id)
    {
        try {
            $this->user_service->suspend($request->status, $id);
            return back()->with(NotificationConstants::SUCCESS_MSG, "User status updated successfully");
        } catch (ModelNotFoundException | InvalidRequestException $th) {
            return back()
                ->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            return back()->withInput($request->all())
                ->with(NotificationConstants::ERROR_MSG, $this->serverErrorMessage);
        }
    }

    public function strike(Request $request, string $id)
    {
        try {
            $this->user_service->strike($request->status, $id);
            return back()->with(NotificationConstants::SUCCESS_MSG, "Strike issued successfully");
        } catch (ModelNotFoundException | InvalidRequestException $th) {
            return back()
                ->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            // throw $th;
            return back()->withInput($request->all())
                ->with(NotificationConstants::ERROR_MSG, $this->serverErrorMessage);
        }
    }

    public function hideUserPost(Request $request, string $id)
    {
        try {
            $this->user_service->hidePost($request, $id);
            return back()->with(NotificationConstants::SUCCESS_MSG, "User post hidden successfully");
        } catch (ModelNotFoundException | InvalidRequestException $th) {
            return back()
                ->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
        Log::error('InvalidRequestException: ' . $th->getMessage() . ' for user ID: ' . $id);

            // throw $th;
            return back()->withInput($request->all())
                ->with(NotificationConstants::ERROR_MSG, $this->serverErrorMessage);
        }
    }

    public function restoreUserPost(Request $request, string $id)
    {
        try {
            $this->user_service->restorePost($request, $id);
            return back()->with(NotificationConstants::SUCCESS_MSG, "User posts restored successfully");
        } catch (ModelNotFoundException | InvalidRequestException $th) {
            return back()
                ->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            // throw $th;
            return back()->withInput($request->all())
                ->with(NotificationConstants::ERROR_MSG, $this->serverErrorMessage);
        }
    }

    public function removeUserPosts(Request $request, string $id)
    {
        try {
            $this->user_service->deleteUserPostsPermanently($request, $id);
            return back()->with(NotificationConstants::SUCCESS_MSG, "User posts permanently removed successfully");
        } catch (ModelNotFoundException | InvalidRequestException $th) {
            return back()
                ->with(NotificationConstants::ERROR_MSG, $th->getMessage());
        } catch (\Throwable $th) {
            // throw $th;
            return back()->withInput($request->all())
                ->with(NotificationConstants::ERROR_MSG, $this->serverErrorMessage);
        }
    }
}
