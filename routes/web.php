<?php

use App\Http\Controllers\Web\AccountDeactivationController;
use App\Http\Controllers\Web\IndexController;
use App\Http\Controllers\Web\InviteController;
use App\Http\Controllers\Web\PasswordResetController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    // return redirect()->route("login");
});

Route:: as('web.')->namespace('Web')->group(function () {
    Route::get('/', function () {
        // return view("emails.auth.pin.admin_password_reset");
        return redirect()->route("login");
    })->name("index");

    Route::get('file/{path}', [IndexController::class, 'readFile'])->name('read_file');

    Route::get('deactivate-account', [AccountDeactivationController::class, 'index'])->name('deactivate-account.index');
    Route::post('deactivate-account/submit', [AccountDeactivationController::class, 'submit'])->name('deactivate-account.submit');

    Route::prefix("invitation")->as('admin.invite.')->group(function () {
        Route::get('{source}/{uuid}', [InviteController::class, "handleCallback"])->name("handleCallback");
        Route::post('{source}/response', [InviteController::class, "response"])->name("response");
        Route::get('{invite}/onboarding/complete', [InviteController::class, "completeOnboarding"])->name("complete-onboarding");
        Route::post('{source}/complete-onboarding/submit', [InviteController::class, "completeOnboardingSubmit"])->name("complete-onboarding-submit");
    });
});

// The link in emails.mobile.auth-password-reset — signed so the code+email
// can't be tampered with in transit, expiring with the pin itself.
Route::get('reset-password', [PasswordResetController::class, 'index'])
    ->middleware('signed')
    ->name('password.reset.form');
Route::post('reset-password', [PasswordResetController::class, 'submit'])
    ->name('password.reset.submit');

Auth::routes();
