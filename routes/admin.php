<?php

use App\Http\Controllers\Api\V1\Admin\PostCategory\PostCategoryController;
use Illuminate\Support\Facades\Route;

Route::middleware("auth:sanctum")->group(function () {
    Route::apiResources([
        "post-categories" => PostCategoryController::class,
    ]);
});
