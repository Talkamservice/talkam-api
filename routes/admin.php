<?php

use App\Http\Controllers\Admin\Authorization\PermissionController;
use App\Http\Controllers\Admin\Authorization\RoleController;
use App\Http\Controllers\Admin\Avatar\AvatarController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Guideline\GuidelineController;
use App\Http\Controllers\Admin\Member\MemberController;
use App\Http\Controllers\Admin\Notification\NotificationController;
use App\Http\Controllers\Admin\Post\PostCategoryController;
use App\Http\Controllers\Admin\Post\PostController;
use App\Http\Controllers\Admin\Profile\ProfileController;
use App\Http\Controllers\Admin\Report\CommentReportController;
use App\Http\Controllers\Admin\Report\GroupReportController;
use App\Http\Controllers\Admin\Report\PostReportController;
use App\Http\Controllers\Admin\User\AccountStatusController;
use App\Http\Controllers\Admin\User\UserController;
use App\Http\Controllers\Web\InviteController;
use Illuminate\Support\Facades\Route;

Route::middleware(["auth"])->group(
    function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('home');

        Route::prefix("profile")->as("profile.")->group(function () {
            Route::get('/', [ProfileController::class, "index"])->name("index");
            Route::post('update-password', [ProfileController::class, "updatePassword"])->name("update-password");
        });

        Route::resources([
            'users' => UserController::class,
            'avatars' => AvatarController::class,
            'post-categories' => PostCategoryController::class,
            'posts' => PostController::class,
            'guidelines'=> GuidelineController::class,
        ]);

        Route::prefix("users")->as("users.")->group(function () {
            Route::post('{id}/suspend', [UserController::class, "suspend"])->name("suspend");
            Route::post('{id}/strike', [UserController::class, "strike"])->name("strike");
            Route::post('{id}/hide-posts', [UserController::class, 'hideUserPost'])->name('hide-posts');
            Route::post('{id}/restore-posts', [UserController::class, 'restoreUserPost'])->name('restore-posts');
            Route::delete('{id}/remove-posts', [UserController::class, 'removeUserPosts'])->name('remove-posts');

        });
       
        Route::prefix("post-categories/{category}")->as("categories.sub-categories.")->group(function () {
            Route::get('index', [PostCategoryController::class, "subCategories"])->name("index");
            Route::get('create', [PostCategoryController::class, "createCategory"])->name("create-sub-category");
            Route::post('store', [PostCategoryController::class, "saveSubCategory"])->name("save-sub-category");
            Route::get('edit/{id}', [PostCategoryController::class, "editSubCategory"])->name("edit-sub-category");
            Route::patch('update/{id}', [PostCategoryController::class, "updateSubCategory"])->name("update-sub-category");
            Route::delete('delete/{id}', [PostCategoryController::class, "deleteSubCategory"])->name("delete-sub-category");
        });


        Route::prefix("account-deactivation-requests")->as("account-deactivation-requests.")->group(function () {
            Route::get('/', [AccountStatusController::class, "deactivationRequestLists"])->name("index");
            Route::post('/submit', [AccountStatusController::class, "submitDeactivationRequest"])->name("submit");
        });

        Route::post('/invitation/send-invite', [InviteController::class, "sendInvite"])->name("invite.sendInvite");
        Route::delete('invitation/{invitation}/delete', [MemberController::class, "invitationDestroy"])->name("invitation.delete");

        Route::prefix('members')->as("members.")->group(function () {
            Route::get('/', [MemberController::class, "index"])->name("index");
            Route::post('{member}/change-role', [MemberController::class, "changeRole"])->name("change-role");
            Route::post('{member}/delete', [MemberController::class, "deleteMember"])->name("delete-member");
        });

        Route::prefix("authorization")->as("authorization.")->group(function () {
            Route::resource('roles', RoleController::class);
            Route::post('roles/{id}/update-permissions', [RoleController::class, "updatePermissions"])->name("roles.update_permissions");
            Route::post('roles/assign', [RoleController::class, "assignRole"])->name("roles.assign");
            Route::resource('permissions', PermissionController::class);
        });

        Route::as("notifications.")->prefix("notifications")->group(function () {
            Route::get("clear-all", [NotificationController::class, "clearAll"])->name("clear-all");
            Route::get("mark-all", [NotificationController::class, "markAll"])->name("mark-all");
            Route::get("{notification}/delete", [NotificationController::class, "destroy"])->name("destroy");
        });

        Route::prefix("reports")->as("reports.")->group(function () {
            Route::get('post/lists', [PostReportController::class, "reportList"])->name("post.lists");
            Route::get('post/show/{id}', [PostReportController::class, "show"])->name("post.show");
            Route::post('post/update-status/{id}', [PostReportController::class, "updateStatus"])->name('post.update-status');
            Route::delete('post/delete/{id}', [PostReportController::class, "deleteReport"])->name('post.delete');

            Route::get('group/lists', [GroupReportController::class, "reportList"])->name("group.lists");
            Route::get('group/show/{id}', [GroupReportController::class, "show"])->name("group.show");

            Route::get('comment/lists', [CommentReportController::class, "reportList"])->name("comment.lists");
            Route::get('comment/show/{id}', [CommentReportController::class, "show"])->name("comment.show");
        });
    }
);


