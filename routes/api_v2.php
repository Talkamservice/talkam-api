<?php

use App\Http\Controllers\Api\V1\Announcement\AnnouncementController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\User\Group\GroupController as V1GroupController;
use App\Http\Controllers\Api\V1\User\Group\GroupMemberController;
use App\Http\Controllers\Api\V1\User\Group\GroupReportController;
use App\Http\Controllers\Api\V1\User\Guideline\GuidelineController;
use App\Http\Controllers\Api\V1\User\Post\RecentViewController;
use App\Http\Controllers\Api\V1\User\Post\SearchController as V1SearchController;
use App\Http\Controllers\Api\V1\User\Post\PostCommentController as V1PostCommentController;
use App\Http\Controllers\Api\V1\User\Post\PostController as V1PostController;
use App\Http\Controllers\Api\V1\User\Post\PostDraftController;
use App\Http\Controllers\Api\V1\User\Post\PostReactionController;
use App\Http\Controllers\Api\V1\User\Post\PostScheduleController;
use App\Http\Controllers\Api\V1\User\UserController as V1UserController;
use App\Http\Controllers\Api\V1\User\Web\PrivacyPolicyController;
use App\Http\Controllers\Api\V2\Auth\PasswordController;
use App\Http\Controllers\Api\V2\Auth\RegisterController;
use App\Http\Controllers\Api\V2\Auth\UsernameController;
use App\Http\Controllers\Api\V2\Auth\VerificationController;
use App\Http\Controllers\Api\V2\Group\GroupController;
use App\Http\Controllers\Api\V2\Group\GroupInviteController;
use App\Http\Controllers\Api\V2\Post\PostCommentController;
use App\Http\Controllers\Api\V2\Post\PostController;
use App\Http\Controllers\Api\V2\Post\SearchController;
use App\Http\Controllers\Api\V2\User\ConsentController;
use App\Http\Controllers\Api\V2\User\FollowController;
use App\Http\Controllers\Api\V2\User\InterestController;
use App\Http\Controllers\Api\V2\User\MoodCheckinController;
use App\Http\Controllers\Api\V2\User\MuteController;
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
// Guest group browsing: guests only see open-access groups.
Route::get("user/groups", [GroupController::class, "index"])->name("groups.index");

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
            Route::post("/", [PostController::class, "store"])->name("store");
            Route::post("reaction", [PostReactionController::class, "reaction"])->name("reaction");
            Route::post("report", [PostReactionController::class, "report"])->name("report");
            Route::post("report-comment", [PostReactionController::class, "reportComment"])->name("report-comment");
            Route::post("not-interested", [PostController::class, "notInterested"])->name("not-interested");
            Route::get("stats/fetch", [V1PostController::class, "fetchStats"])->name("fetch-stats");
            Route::post("stats/save", [V1PostController::class, "saveStats"])->name("save-stats");
            Route::get("{post}", [V1PostController::class, "show"])->name("show");
        });

        Route::prefix("post-comments")->as("post-comments.")->group(function () {
            Route::get("/", [PostCommentController::class, "index"])->name("index");
            Route::post("/", [PostCommentController::class, "store"])->name("store");
            Route::post("reaction", [V1PostCommentController::class, "reaction"])->name("reaction");
        });

        Route::apiResources([
            "post-drafts" => PostDraftController::class,
            "post-schedules" => PostScheduleController::class,
        ]);

        Route::prefix("follows")->as("follows.")->group(function () {
            Route::post("toggle", [FollowController::class, "toggle"])->name("toggle");
            Route::get("following", [FollowController::class, "following"])->name("following");
            Route::get("followers", [FollowController::class, "followers"])->name("followers");
        });

        Route::prefix("mutes")->as("mutes.")->group(function () {
            Route::post("toggle", [MuteController::class, "toggle"])->name("toggle");
            Route::get("/", [MuteController::class, "index"])->name("index");
        });

        Route::prefix("blocked-users")->as("blocked-users.")->group(function () {
            Route::get("/", [V1UserController::class, "blockUserLists"])->name("index");
            Route::post("/add", [V1UserController::class, "blockUser"])->name("add");
        });

        Route::prefix("guidelines")->as("guidelines.")->group(function () {
            Route::get("/", [GuidelineController::class, "index"])->name("index");
            Route::get("/{guideline}", [GuidelineController::class, "show"])->name("show");
        });

        Route::prefix("groups")->as("groups.")->group(function () {
            Route::get("suggested", [GroupController::class, "suggested"])->name("suggested");
            Route::post("follow", [GroupController::class, "followToggle"])->name("follow");
            Route::get("members/following", [GroupController::class, "following"])->name("members.following");
            Route::post("/unfollow-group", [GroupMemberController::class, "unfollow"])->name("members.unfollow-group");

            Route::prefix("reports")->as("reports.")->group(function () {
                Route::post("members/create", [GroupMemberController::class, "reportMember"])->name("report-members");
                Route::post("create", [GroupReportController::class, "report"])->name("report");
            });

            Route::post("invites/accept", [GroupInviteController::class, "accept"])->name("invites.accept");
            Route::post("{group}/invite", [GroupInviteController::class, "invite"])->name("invite");
            Route::post("{group}/request-access", [GroupMemberController::class, "requestAccess"])->name("request-access");
            Route::post("{group}/update-access-request", [GroupMemberController::class, "updateAccessRequest"])->name("update-access-request");
            Route::get("{group}", [V1GroupController::class, "show"])->name("show");
        });

        Route::apiResource("group-members", GroupMemberController::class);

        Route::prefix("recents")->as("recents.")->group(function () {
            Route::get("fetch", [RecentViewController::class, "index"])->name("fetch");
        });

        Route::prefix("search")->as("search.")->group(function () {
            Route::get("/", [SearchController::class, "index"])->name("index");
            Route::get("recent", [V1SearchController::class, "recent"])->name("recent");
            Route::get("trending", [V1SearchController::class, "trending"])->name("trending");
            Route::delete("{id}/delete", [V1SearchController::class, "destroy"])->name("destroy");
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
