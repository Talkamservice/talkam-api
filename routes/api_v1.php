<?php

use App\Http\Controllers\Api\V1\Announcement\AnnouncementController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\VerificationController;
use App\Http\Controllers\Api\V1\Feedback\FeedbackController;
use App\Http\Controllers\Api\V1\General\AuthController;
use App\Http\Controllers\Api\V1\Webhook\WehbookHandlingController;
use App\Http\Controllers\Api\V1\Location\LocationController;
use App\Http\Controllers\Api\V1\User\Finance\PlansController;
use App\Http\Controllers\Api\V1\User\Finance\SubscriptionsController;
use App\Http\Controllers\Api\V1\User\Group\GroupController;
use App\Http\Controllers\Api\V1\User\Group\GroupMemberController;
use App\Http\Controllers\Api\V1\User\Group\GroupReportController;
use App\Http\Controllers\Api\V1\User\Guideline\GuidelineController;
use App\Http\Controllers\Api\V1\User\Messaging\ConversationController;
use App\Http\Controllers\Api\V1\User\Messaging\MessagingController;
use App\Http\Controllers\Api\V1\User\Notification\NotificationController;
use App\Http\Controllers\Api\V1\User\Payment\PaymentController;
use App\Http\Controllers\Api\V1\User\Post\PostAttachmentController;
use App\Http\Controllers\Api\V1\User\Post\PostCommentController;
use App\Http\Controllers\Api\V1\User\Post\PostController;
use App\Http\Controllers\Api\V1\User\Post\PostDraftController;
use App\Http\Controllers\Api\V1\User\Post\PostPollController;
use App\Http\Controllers\Api\V1\User\Post\PostReactionController;
use App\Http\Controllers\Api\V1\User\Post\PostScheduleController;
use App\Http\Controllers\Api\V1\User\Post\RecentViewController;
use App\Http\Controllers\Api\V1\User\Post\SearchController;
use App\Http\Controllers\Api\V1\User\PostCategory\PostCategoryController;
use App\Http\Controllers\Api\V1\User\Promotion\PromotionController;
use App\Http\Controllers\Api\V1\User\Promotion\PromotionPricingSettingController;
use App\Http\Controllers\Api\V1\User\UserController;
use App\Http\Controllers\Api\V1\User\Web\FaqController;
use App\Http\Controllers\Api\V1\User\Web\PrivacyPolicyController;
use App\Http\Controllers\Api\V1\User\Web\TermAndConditionController;
use App\Http\Controllers\Api\V1\Waitlist\WaitlistController;
use App\Models\Promotion;
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

Route::post('/webhook/verifications', [WehbookHandlingController::class, 'handleWebhook'])->name('handle-webhook');

Route::prefix("auth")->as("auth.")->group(function () {
    Route::post("/register", [RegisterController::class, "register"])->name("register");
    Route::post("/login/preview", [LoginController::class, "loginPreview"])->name("login_preview");
    Route::post("/oauth-login", [LoginController::class, "oauthLogin"]);
    Route::post("/login", [LoginController::class, "login"])->name("login");

    Route::prefix("password")->as("password.")->group(function () {
        Route::post('/forgot', [PasswordController::class, 'forgotPassword'])->name("forgot_password");
        Route::post("/reset", [PasswordController::class, "resetPassword"])->name("reset_password");
    });
    Route::prefix("otp")->as("otp.")->group(function () {
        Route::post('/request', [VerificationController::class, 'request'])->name("request");
        Route::post("/verify", [VerificationController::class, "verify"])->name("verify");
    });
});

Route::get("profile/avatars", [UserController::class, "listAvatars"])->name("avatars.list");

Route::prefix("location")->as("location.")->group(function () {
    Route::get('countries', [LocationController::class, "countries"])->name("countries");
    Route::get('states', [LocationController::class, "states"])->name("states");
});

Route::middleware(["auth:sanctum"])->group(function () {
    Route::prefix("user")->as("user.")->middleware(["pricingCountry"])->group(function () {
        Route::get("/me", [UserController::class, "me"])->name("me");
        Route::get("/get-by-username/{username}", [UserController::class, "getByUsername"])->name("get-by-username");

        Route::prefix("profile")->as("profile.")->group(function () {
            Route::post("/upload-avatar", [UserController::class, "uploadAvatar"])->name("upload.avatar");
            Route::post("/update", [UserController::class, "update"])->name("update");
            Route::post("interests/add-remove", [UserController::class, "saveInterest"])->name("save-interest");
            Route::post("erase-account-data", [UserController::class, "eraseAccount"])->name("erase-account");
            Route::post("delete-account", [UserController::class, "deleteAccount"])->name("delete-account");
            Route::get("fetch", [UserController::class, "getProfile"])->name("get-profile");
            Route::post("link-social-account", [UserController::class, "linkSocialAccount"])->name("link-social-account");
            Route::post("unlink-social-account", [UserController::class, "unlinkSocialAccount"])->name("unlink-social-account");
        });

        Route::prefix("post-categories")->as("post-categories.")->group(function () {
            Route::get("/", [PostCategoryController::class, "index"])->name("index");
            Route::get("{id}/show", [PostCategoryController::class, "show"])->name("show");
            Route::post("follow", [PostCategoryController::class, "follow"])->name("follow");
            Route::get("following", [PostCategoryController::class, "following"])->name("following");
            Route::get("sub-categories", [PostCategoryController::class, "subCategories"])->name("sub-categories");
            Route::get("following", [PostCategoryController::class, "following"])->name("following");
            Route::get("sub-categories", [PostCategoryController::class, "subCategories"])->name("sub-categories");
            Route::get("merged-categories", [PostCategoryController::class, "mergedCategories"])->name("merged-categories");
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
            "groups" => GroupController::class,
            "group-members" => GroupMemberController::class,
            "guidelines" => GuidelineController::class,
        ]);

        Route::prefix("posts")->as("posts.")->group(function () {
            Route::post("reaction", [PostReactionController::class, "reaction"])->name("reaction");
            Route::post("report", [PostReactionController::class, "report"])->name("report");
            Route::post("report-comment", [PostReactionController::class, "reportComment"])->name("report-comment");
            Route::get("filter", [PostController::class, "filter"])->name("filter");
            Route::get("actions/get-comment-posts", [PostController::class, "postWithComments"])->name("get-comment-posts");
            Route::get("actions/get-upvotes", [PostController::class, "postWithLikes"])->name("get-liked-posts");
            Route::get("media/fetch", [PostController::class, "media"])->name("get-all-media");

            Route::get("stats/fetch", [PostController::class, "fetchStats"])->name("fetch-stats");
            Route::post("stats/save", [PostController::class, "saveStats"])->name("save-stats");
        });

        Route::prefix("trendings")->as("trendings")->group(function () {
            Route::get("fetch", [PostController::class, "trending"])->name("fetch");
        });

        Route::prefix("search")->as("search")->group(function () {
            Route::get("/", [SearchController::class, "index"])->name("index");
            Route::get("recent", [SearchController::class, "recent"])->name("recent");
            Route::get("trending", [SearchController::class, "trending"])->name("trending");
            Route::get("/username", [UserController::class, "search"])->name("search");
        });

        Route::prefix("groups")->as("groups.")->group(function () {
            Route::post("{group}/request-access", [GroupMemberController::class, "requestAccess"])->name("request-access");
            Route::post("{group}/update-access-request", [GroupMemberController::class, "updateAccessRequest"])->name("update-access-request");

            Route::prefix("reports")->as("reports.")->group(function () {
                Route::post("members/create", [GroupMemberController::class, "reportMember"])->name("report-members");
                Route::post("create", [GroupReportController::class, "report"])->name("report");
            });

            Route::get("members/list", [GroupMemberController::class, "list"])->name("members.list");
            Route::get("members/following", [GroupMemberController::class, "following"])->name("members.following");
            Route::post("/unfollow-group", [GroupMemberController::class, "unfollow"])->name("members.unfollow-group");
            Route::post("members/{id}/suspend", [GroupMemberController::class, "suspendMember"])->name("members.suspend");
            // Route::post("member/suspend", [GroupMemberController::class, "suspend"])->name("members.suspend");
        });

        Route::prefix("recents")->as("recents")->group(function () {
            Route::get("fetch", [RecentViewController::class, "index"])->name("fetch");
        });

        Route::prefix("promotions")->as("promotions")->group(function () {
            Route::get("/", [PromotionController::class, "index"])->name("index");
            Route::get("{promotion}/show", [PromotionController::class, "show"])->name("show");
            Route::post("initiate", [PromotionController::class, "initiate"])->name("initiate");
            Route::post("{id}/update", [PromotionController::class, "update"])->name("update");
            Route::post("{id}/reinitiate", [PromotionController::class, "reinitiate"])->name("reinitiate");
            Route::delete("{promotion}/delete", [PromotionController::class, "delete"])->name("delete");
        });

        Route::prefix("post-comments")->as("post-comments.")->group(function () {
            Route::post("reaction", [PostCommentController::class, "reaction"])->name("reaction");
        });

        Route::prefix("messaging")->as("messaging.")->group(function () {
            Route::resource("conversations", ConversationController::class);

            Route::prefix("conversations")->as("conversations.")->group(function () {
                Route::post("update-status", [ConversationController::class, "updateStatus"])->name("update-status");
                Route::post("report", [ConversationController::class, "report"])->name("report");
                Route::get("/current/fetch", [ConversationController::class, "currentConversation"])->name("current-conversation");
                Route::get("/pending-requests", [ConversationController::class, "pendingRequests"])->name("pending-request");
            });

            Route::prefix("messages")->as("conversations.")->group(function () {
                Route::get("list", [MessagingController::class, "list"])->name("get-messages");
                Route::post("/send", [MessagingController::class, "sendMessage"])->name("send-message");
                Route::delete("/delete/{messageId}", [MessagingController::class, "deleteMessage"])->name("delete-message");
            });
        });

        Route::prefix("finance")->as("finance.")->group(function () {
            Route::prefix("plans")->as("plans")->group(function () {
                Route::get("/", [PlansController::class,  "index"])->name("index");
                Route::get("{plan}/show", [PlansController::class,  "show"])->name("show");
            });

            Route::prefix("subscriptions")->as("subscriptions.")->group(function () {
                Route::get("/", [SubscriptionsController::class,  "index"])->name("index");
                Route::get("{subscription}/show", [SubscriptionsController::class,  "show"])->name("show");
                Route::post("initiate", [SubscriptionsController::class,  "initiate"])->name("initiate");
                Route::post("{subscription}/cancel", [SubscriptionsController::class,  "cancel"])->name("cancel");
            });

            Route::prefix("payments")->as("payments")->group(function () {
                Route::post("callback", [PaymentController::class,  "callback"])->name("callback");
            });
        });


        Route::prefix("notifications")->as("notifications.")->group(function () {
            Route::get("list", [NotificationController::class, "index"])->name("index");
            Route::get("{notification}/show", [NotificationController::class, "show"])->name("show");
            Route::post("clear-all", [NotificationController::class, "clearAll"])->name("clear-all");
            Route::post("mark-all", [NotificationController::class, "markAll"])->name("mark-all");
            Route::get("preference/fetch", [NotificationController::class, "notificationPerference"])->name("notification-perference");
            Route::post("preference/save", [NotificationController::class, "saveNotificationPerference"])->name("save-notification-perference");
            Route::post("thread/add", [NotificationController::class, "sendThreadNotification"])->name("send-thread-notification");
            Route::get("get-notification-status", [NotificationController::class, "notificationStatus"])->name("get-notification-status");
        });

        Route::prefix("posts")->as("posts.")->group(function () {
            Route::get("promotions/list", [PostController::class, "getPrmotedPosts"])->name("promotions/list");
        });
        Route::prefix("groups")->as("groups.")->group(function () {
            Route::get("promotions/list", [GroupController::class, "getPromoteGroups"])->name("promotions/list");
        });
        Route::get("promotion-pricings/{id}/get", [PromotionPricingSettingController::class, "promotionPricing"])->name("promotion-pricings.get");
    });
});

Route::get("user/terms-and-conditions", [TermAndConditionController::class, "index"])->name("terms-and-conditions.list");
Route::get("user/privacy-policies", [PrivacyPolicyController::class, "index"])->name("privacy-policies.list");
Route::post("user/feedback", [FeedbackController::class, "save"])->name("feedback.save");
Route::get("user/feedback-options", [FeedbackController::class, "feedbackOptions"])->name("feedback-options");
Route::get("user/faqs", [FaqController::class, "index"])->name("faqs.index");
Route::post("user/waitlist/save", [WaitlistController::class, "save"])->name("waitlist.save");

//Guest mode
Route::prefix('user')->as('user.')->group(function () {
    Route::prefix('post-categories')->as('post-categories.')->group(function () {
        Route::get('/', [PostCategoryController::class, 'index'])->name('index');
        Route::get('{id}/show', [PostCategoryController::class, 'show'])->name('show');
    });

    Route::prefix('posts')->as('posts.')->group(function () {
        Route::get('/', [PostController::class, 'index'])->name('index');
        Route::get('/{post}', [PostController::class, 'show'])->name('show');
    });

    Route::prefix('guidelines')->as('guidelines.')->group(function () {
        Route::get('/', [GuidelineController::class, 'index'])->name('index');
        Route::get('/{guideline}', [GuidelineController::class, 'show'])->name('show');
    });

    Route::prefix('groups')->as('groups.')->group(function () {
        Route::get('/', [GroupController::class, 'index'])->name('index');
    });

    Route::prefix('post-comments')->as('post-comments.')->group(function () {
        Route::get('/', [PostCommentController::class, 'index'])->name('index');
        Route::get('/{post_comment}', [PostCommentController::class, 'show'])->name('show');
    });

    Route::prefix("search")->as("search")->group(function () {
        Route::get("/", [SearchController::class, "index"])->name("index");
        Route::get("recent", [SearchController::class, "recent"])->name("recent");
        Route::get("trending", [SearchController::class, "trending"])->name("trending");
        Route::get("suggestions", [SearchController::class, "suggestions"])->name("suggestions");
        Route::delete("{id}/delete", [SearchController::class, "destroy"])->name("destroy");
        Route::get("/username", [UserController::class, "search"])->name("search");
        Route::delete("{user_id}/delete-all", [SearchController::class, "DeleteAllSearch"])->name("recent.delete-all");
    });

    Route::prefix("announcements")->as("announcements.")->group(function () {
        Route::get("/", [AnnouncementController::class, "index"]);
        Route::get("{id}/show", [AnnouncementController::class, "show"])->name('show');
    });

    Route::prefix("announcements")->as("announcements.")->group(function () {
        Route::get("/", [AnnouncementController::class, "index"]);
        Route::get("{id}/show", [AnnouncementController::class, "show"])->name('show');
    });

    Route::prefix("post-categories")->as("post-categories.")->group(function () {
        Route::get("merged-categories", [PostCategoryController::class, "mergedCategories"])->name("merged-categories");
    });

    Route::prefix("posts")->as("posts.")->group(function () {
        Route::get("promotions/list", [PostController::class, "getPrmotedPosts"])->name("promotions/list");
    });
    Route::prefix("groups")->as("groups.")->group(function () {
        Route::get("promotions/list", [GroupController::class, "getPromoteGroups"])->name("promotions/list");
    });

});
Route::post('/broadcasting/auth', [AuthController::class, "authenticate"])->middleware('auth:sanctum');
