<?php

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
    return redirect()->route("login");
    // return view('welcome');
});

Route::as('web.')->namespace('Web')->group(function () {
    Route::get('file/{path}', [IndexController::class, 'readFile'])->name('read_file');
});

Auth::routes();
