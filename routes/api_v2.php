<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V2\Auth\PasswordController;
use App\Http\Controllers\Api\V2\Auth\RegisterController;
use App\Http\Controllers\Api\V2\Auth\UsernameController;
use App\Http\Controllers\Api\V2\Auth\VerificationController;
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
