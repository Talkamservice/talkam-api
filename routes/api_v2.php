<?php

use App\Http\Controllers\Api\V1\Announcement\AnnouncementController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\User\Post\PostController as V1PostController;
use App\Http\Controllers\Api\V1\User\Post\PostReactionController;
use App\Http\Controllers\Api\V1\User\UserController as V1UserController;
use App\Http\Controllers\Api\V1\User\Web\PrivacyPolicyController;
use App\Http\Controllers\Api\V2\Auth\PasswordController;
use App\Http\Controllers\Api\V2\Auth\RegisterController;
use App\Http\Controllers\Api\V2\Auth\UsernameController;
use App\Http\Controllers\Api\V2\Auth\VerificationController;
use App\Http\Controllers\Api\V2\Post\PostController;
use App\Http\Controllers\Api\V2\User\ConsentController;
use App\Http\Controllers\Api\V2\User\InterestController;
use App\Http\Controllers\Api\V2\User\MoodCheckinController;
use App\Http\Controllers\Api\V2\User\OnboardingController;
use App\Http\Controllers\Api\V2\User\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V2 Routes
|--------------------------------------------------------------------------
|
| v2 is a parallel lane: mounted at api/v2 with controllers under
| App\Http\Controllers\Api\V2. Features triaged as "reuse" point at the
| existing V1 controllers; v1 routes and behavior stay untouched.
|
*/

Route::prefix("auth")->as("auth.")->group(function () {
    Route::post("/register", [RegisterController::class, "register"])->name("register");
    Route::post("/oauth-login", [LoginController::class, "oauthLogin"])->name("oauth_login");
    Route::post("/login", [LoginController::class, "login"])->name("login");

    Route::get("/username/available", [UsernameController::class, "available"])
        ->middleware("throttle:30,1")
        ->name("username.available");

    Route::prefix("password")->as("password.")->group(function () {
        Route::post('/forgot', [PasswordController::class, 'forgotPassword'])
            ->middleware("throttle:5,1")
            ->name("forgot_password");
        Route::post("/reset", [PasswordController::class, "resetPassword"])->name("reset_password");
    });
    Route::prefix("otp")->as("otp.")->group(function () {
        Route::post('/request', [VerificationController::class, 'request'])
            ->middleware("throttle:5,1")
            ->name("request");
        Route::post("/verify", [VerificationController::class, "verify"])->name("verify");
    });
});

// Public, matching v1's placement of these endpoints.
Route::get("profile/avatars", [V1UserController::class, "listAvatars"])->name("avatars.list");
Route::get("user/privacy-policies", [PrivacyPolicyController::class, "index"])->name("privacy-policies.list");

Route::middleware(["auth:sanctum"])->group(function () {
    Route::prefix("user")->as("user.")->middleware(["pricingCountry"])->group(function () {
        Route::get("/me", [UserController::class, "me"])->name("me");

        Route::get("interest-topics", [InterestController::class, "topics"])->name("interest-topics");

        Route::prefix("profile")->as("profile.")->group(function () {
            Route::post("/update", [V1UserController::class, "update"])->name("update");
            Route::post("interests", [InterestController::class, "sync"])->name("interests.sync");
        });

        Route::prefix("onboarding")->as("onboarding.")->group(function () {
            Route::post("user-type", [OnboardingController::class, "userType"])->name("user-type");
            Route::post("complete", [OnboardingController::class, "complete"])->name("complete");
        });

        Route::get("consents", [ConsentController::class, "index"])->name("consents.index");
        Route::post("consents", [ConsentController::class, "store"])->name("consents.store");

        Route::prefix("posts")->as("posts.")->group(function () {
            Route::get("/", [PostController::class, "index"])->name("index");
            Route::post("reaction", [PostReactionController::class, "reaction"])->name("reaction");
            Route::get("stats/fetch", [V1PostController::class, "fetchStats"])->name("fetch-stats");
            Route::post("stats/save", [V1PostController::class, "saveStats"])->name("save-stats");
            Route::get("{post}", [V1PostController::class, "show"])->name("show");
        });

        Route::prefix("mood-checkins")->as("mood-checkins.")->group(function () {
            Route::get("today", [MoodCheckinController::class, "today"])->name("today");
            Route::post("/", [MoodCheckinController::class, "store"])->name("store");
        });

        Route::prefix("announcements")->as("announcements.")->group(function () {
            Route::get("/", [AnnouncementController::class, "index"])->name("index");
            Route::get("{id}/show", [AnnouncementController::class, "show"])->name("show");
        });
    });
});
