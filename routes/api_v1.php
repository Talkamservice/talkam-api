<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\VerificationController;
use App\Http\Controllers\Api\V1\User\Messaging\ConversationController;
use App\Http\Controllers\Api\V1\User\Post\PostAttachmentController;
use App\Http\Controllers\Api\V1\User\Post\PostCommentController;
use App\Http\Controllers\Api\V1\User\Post\PostController;
use App\Http\Controllers\Api\V1\User\Post\PostDraftController;
use App\Http\Controllers\Api\V1\User\Post\PostPollController;
use App\Http\Controllers\Api\V1\User\Post\PostReactionController;
use App\Http\Controllers\Api\V1\User\Post\PostScheduleController;
use App\Http\Controllers\Api\V1\User\Post\RecentViewController;
use App\Http\Controllers\Api\V1\User\PostCategory\MessagingController;
use App\Http\Controllers\Api\V1\User\PostCategory\PostCategoryController;
use App\Http\Controllers\Api\V1\User\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix("auth")->as("auth.")->group(function () {
    Route::post("/register", [RegisterController::class, "register"])->name("register");
    Route::post("/login/preview", [LoginController::class, "loginPreview"])->name("login_preview");
    Route::post("/oauth-login", [LoginController::class, "oauthLogin"]);
    Route::post("/login", [LoginController::class, "login"])->name("login");

    Route::prefix("password")->as("password.")->group(function () {
        Route::post('/forgot', [PasswordController::class, 'forgotPassword'])->name("forgot_password");
        Route::post("/reset", [PasswordController::class,  "resetPassword"])->name("reset_password");
    });
    Route::prefix("otp")->as("otp.")->group(function () {
        Route::post('/request', [VerificationController::class, 'request'])->name("request");
        Route::post("/verify", [VerificationController::class,  "verify"])->name("verify");
    });
});

Route::get("profile/avatars", [UserController::class,  "listAvatars"])->name("avatars.list");

Route::middleware(["auth:sanctum"])->group(function () {
    Route::prefix("user")->as("user.")->group(function () {
        Route::get("/me", [UserController::class,  "me"])->name("me");

        Route::prefix("profile")->as("profile.")->group(function () {
            Route::post("/upload-avatar", [UserController::class,  "uploadAvatar"])->name("upload.avatar");
            Route::post("/update", [UserController::class,  "update"])->name("update");
            Route::post("interests/add-remove", [UserController::class,  "saveInterest"])->name("save-interest");

            Route::post("erase-account-data", [UserController::class,  "eraseAccount"])->name("erase-account");
            Route::post("delete-account", [UserController::class,  "deleteAccount"])->name("delete-account");

            Route::get("fetch", [UserController::class,  "getProfile"])->name("get-profile");
        });

        Route::prefix("post-categories")->as("post-categories.")->group(function () {
            Route::get("/", [PostCategoryController::class, "index"])->name("index");
            Route::get("{id}/show", [PostCategoryController::class, "show"])->name("show");
        });

        Route::prefix("blocked-users")->as("blocked-users.")->group(function () {
            Route::get("/", [UserController::class, "blockUserLists"])->name("index");
            Route::post("/add", [UserController::class, "blockUser"])->name("blocked-users.add");
        });

        Route::apiResources([
            "posts" => PostController::class,
            "post-attachments" => PostAttachmentController::class,
            "post-polls" => PostPollController::class,
            "post-comments" => PostCommentController::class,
            "post-schedules" => PostScheduleController::class,
            "post-drafts" => PostDraftController::class,
            "recent-views" => RecentViewController::class,
        ]);

        Route::prefix("posts")->as("posts.")->group(function () {
            Route::post("reaction", [PostReactionController::class, "reaction"])->name("reaction");
            Route::post("report", [PostReactionController::class, "report"])->name("report");
            Route::get("filter", [PostController::class, "filter"])->name("filter");
        });

        Route::prefix("trendings")->as("trendings")->group(function () {
            Route::get("fetch", [PostController::class, "trending"])->name("fetch");
        });

        Route::prefix("recent-views")->as("trendings")->group(function () {
            Route::post("add", [PostController::class, "trending"])->name("fetch");
        });

        Route::prefix("post-comments")->as("post-comments.")->group(function () {
            Route::post("reaction", [PostCommentController::class, "reaction"])->name("reaction");
        });

        Route::prefix("messaging")->as("messaging.")->group(function () {
            Route::resource("conversations", ConversationController::class);

            Route::prefix("conversations")->as("conversations.")->group(function () {
                Route::post("update-status", [ConversationController::class, "updateStatus"])->name("update-status");
                Route::post("report", [ConversationController::class, "report"])->name("report");
            });
        });
    });
});
