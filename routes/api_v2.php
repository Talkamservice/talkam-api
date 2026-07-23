<?php

use App\Http\Controllers\Api\V1\Announcement\AnnouncementController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\User\Group\GroupController as V1GroupController;
use App\Http\Controllers\Api\V1\User\Group\GroupMemberController;
use App\Http\Controllers\Api\V1\User\Group\GroupReportController;
use App\Http\Controllers\Api\V1\User\Guideline\GuidelineController;
use App\Http\Controllers\Api\V1\User\Messaging\ConversationController as V1ConversationController;
use App\Http\Controllers\Api\V1\User\Messaging\MessagingController;
use App\Http\Controllers\Api\V1\User\Notification\NotificationController;
use App\Http\Controllers\Api\V1\User\Post\RecentViewController;
use App\Http\Controllers\Api\V1\User\Post\SearchController as V1SearchController;
use App\Http\Controllers\Api\V1\User\Post\PostCommentController as V1PostCommentController;
use App\Http\Controllers\Api\V1\User\Post\PostController as V1PostController;
use App\Http\Controllers\Api\V1\User\Post\PostDraftController;
use App\Http\Controllers\Api\V1\User\Post\PostReactionController;
use App\Http\Controllers\Api\V1\User\Post\PostScheduleController;
use App\Http\Controllers\Api\V1\User\UserController as V1UserController;
use App\Http\Controllers\Api\V1\User\Web\PrivacyPolicyController;
use App\Http\Controllers\Api\V2\Auth\LoginController as V2LoginController;
use App\Http\Controllers\Api\V2\Auth\PasswordController;
use App\Http\Controllers\Api\V2\Auth\RegisterController;
use App\Http\Controllers\Api\V2\Auth\TwoFactorController;
use App\Http\Controllers\Api\V2\Auth\UsernameController;
use App\Http\Controllers\Api\V2\Auth\VerificationController;
use App\Http\Controllers\Api\V2\Group\GroupController;
use App\Http\Controllers\Api\V2\Group\GroupInviteController;
use App\Http\Controllers\Api\V2\Messaging\ConversationController as V2ConversationController;
use App\Http\Controllers\Api\V2\Post\PostCommentController;
use App\Http\Controllers\Api\V2\Post\PostController;
use App\Http\Controllers\Api\V2\Post\SearchController;
use App\Http\Controllers\Api\V2\Finance\PaymentCallbackController;
use App\Http\Controllers\Api\V2\Therapist\BookingController;
use App\Http\Controllers\Api\V2\Therapist\SessionController;
use App\Http\Controllers\Api\V2\Therapist\SessionRequestController;
use App\Http\Controllers\Api\V2\Therapist\SessionReviewController;
use App\Http\Controllers\Api\V2\Webhook\AvProviderWebhookController;
use App\Http\Controllers\Api\V2\Therapist\TherapistApplicationController;
use App\Http\Controllers\Api\V2\Therapist\TherapistDirectoryController;
use App\Http\Controllers\Api\V2\Therapist\TherapistPayoutController;
use App\Http\Controllers\Api\V2\User\ConsentController;
use App\Http\Controllers\Api\V2\User\DataExportController;
use App\Http\Controllers\Api\V2\User\FollowController;
use App\Http\Controllers\Api\V2\User\InterestController;
use App\Http\Controllers\Api\V2\User\MoodCheckinController;
use App\Http\Controllers\Api\V2\User\MuteController;
use App\Http\Controllers\Api\V2\User\NotificationPreferenceController;
use App\Http\Controllers\Api\V2\User\OnboardingController;
use App\Http\Controllers\Api\V2\User\PaymentMethodController;
use App\Http\Controllers\Api\V2\User\PrivacySettingController;
use App\Http\Controllers\Api\V2\User\ProfileController;
use App\Http\Controllers\Api\V2\User\UserController;
use App\Http\Controllers\Api\V2\User\UserReportController;
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
    Route::post("/login", [V2LoginController::class, "login"])->name("login");
    Route::post("/2fa/verify", [TwoFactorController::class, "verify"])->name("2fa.verify");

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
            Route::post("/update", [ProfileController::class, "update"])->name("update");
            Route::post("/upload-avatar", [V1UserController::class, "uploadAvatar"])->name("upload.avatar");
            Route::post("interests", [InterestController::class, "sync"])->name("interests.sync");
            Route::post("delete-account", [\App\Http\Controllers\Api\V2\User\DeleteAccountController::class, "deleteAccount"])->name("delete-account");
        });

        Route::get("notification-preferences", [NotificationPreferenceController::class, "index"])->name("notification-preferences.index");
        Route::post("notification-preferences", [NotificationPreferenceController::class, "store"])->name("notification-preferences.store");

        Route::get("privacy-settings", [PrivacySettingController::class, "index"])->name("privacy-settings.index");
        Route::post("privacy-settings", [PrivacySettingController::class, "store"])->name("privacy-settings.store");

        Route::get("payment-methods", [PaymentMethodController::class, "index"])->name("payment-methods.index");
        Route::delete("payment-methods/{id}", [PaymentMethodController::class, "destroy"])->name("payment-methods.destroy");
        Route::post("security/payment-pin", [PaymentMethodController::class, "setPaymentPin"])->name("security.payment-pin");

        Route::post("data-export", [DataExportController::class, "store"])->name("data-export.store");
        Route::get("data-export/latest", [DataExportController::class, "latest"])->name("data-export.latest");

        Route::prefix("notifications")->as("notifications.")->group(function () {
            Route::get("list", [NotificationController::class, "index"])->name("index");
            Route::post("clear-all", [NotificationController::class, "clearAll"])->name("clear-all");
            Route::post("mark-all", [NotificationController::class, "markAll"])->name("mark-all");
            Route::get("get-notification-status", [NotificationController::class, "notificationStatus"])->name("get-notification-status");
        });

        Route::post("user-reports", [UserReportController::class, "store"])->name("user-reports.store");

        Route::get("drawer", [\App\Http\Controllers\Api\V2\User\DrawerController::class, "index"])->name("drawer");

        Route::prefix("messaging")->as("messaging.")->group(function () {
            Route::get("conversations", [V2ConversationController::class, "index"])->name("conversations.index");
            Route::post("conversations", [V2ConversationController::class, "store"])->name("conversations.store");

            Route::prefix("conversations")->as("conversations.")->group(function () {
                Route::post("update-status", [V1ConversationController::class, "updateStatus"])->name("update-status");
                Route::post("report", [V2ConversationController::class, "report"])->name("report");
                Route::get("/current/fetch", [V1ConversationController::class, "currentConversation"])->name("current-conversation");
                Route::get("/pending-requests", [V2ConversationController::class, "pendingRequests"])->name("pending-requests");

                foreach (["mute", "unmute", "archive", "unarchive", "star", "unstar", "seen"] as $action) {
                    Route::post($action, [V2ConversationController::class, "state"])
                        ->defaults("action", $action)->name($action);
                }

                Route::get("{conversation}", [V1ConversationController::class, "show"])->name("show");
            });

            Route::prefix("messages")->as("messages.")->group(function () {
                Route::get("list", [\App\Http\Controllers\Api\V2\Messaging\MessageController::class, "list"])->name("list");
                Route::post("/send", [\App\Http\Controllers\Api\V2\Messaging\MessageController::class, "send"])->name("send");
                Route::post("edit", [\App\Http\Controllers\Api\V2\Messaging\MessageActionController::class, "edit"])->name("edit");
                Route::post("reply", [\App\Http\Controllers\Api\V2\Messaging\MessageActionController::class, "reply"])->name("reply");
                Route::post("forward", [\App\Http\Controllers\Api\V2\Messaging\MessageActionController::class, "forward"])->name("forward");
                Route::post("add-reaction", [\App\Http\Controllers\Api\V2\Messaging\MessageActionController::class, "addReaction"])->name("add-reaction");
                Route::post("remove-reaction", [\App\Http\Controllers\Api\V2\Messaging\MessageActionController::class, "removeReaction"])->name("remove-reaction");
                Route::post("pin", [\App\Http\Controllers\Api\V2\Messaging\MessageActionController::class, "pin"])->name("pin");
                Route::post("unpin", [\App\Http\Controllers\Api\V2\Messaging\MessageActionController::class, "unpin"])->name("unpin");
                Route::post("bulk-mark-as-read", [\App\Http\Controllers\Api\V2\Messaging\MessageController::class, "bulkMarkRead"])->name("bulk-mark-as-read");
                Route::get("search", [\App\Http\Controllers\Api\V2\Messaging\MessageController::class, "search"])->name("search");
                Route::post("typing", [\App\Http\Controllers\Api\V2\Messaging\MessageController::class, "typing"])->name("typing");
                Route::delete("{message}", [\App\Http\Controllers\Api\V2\Messaging\MessageActionController::class, "destroy"])->name("destroy");
            });

            Route::prefix("drafts")->as("drafts.")->group(function () {
                Route::post("save", [\App\Http\Controllers\Api\V2\Messaging\DraftController::class, "save"])->name("save");
                Route::get("get", [\App\Http\Controllers\Api\V2\Messaging\DraftController::class, "get"])->name("get");
                Route::delete("delete", [\App\Http\Controllers\Api\V2\Messaging\DraftController::class, "delete"])->name("delete");
            });

            Route::post("presence", [\App\Http\Controllers\Api\V2\Messaging\PresenceController::class, "update"])->name("presence");
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

        Route::prefix("therapists")->as("therapists.")->group(function () {
            Route::get("/", [TherapistDirectoryController::class, "index"])->name("index");
            Route::get("{therapist}/slots", [TherapistDirectoryController::class, "slots"])->name("slots");
            Route::get("{therapist}/reviews", [SessionReviewController::class, "index"])->name("reviews");
            Route::get("{therapist}", [TherapistDirectoryController::class, "show"])->name("show");
        });

        Route::prefix("bookings")->as("bookings.")->group(function () {
            Route::get("/", [BookingController::class, "index"])->name("index");
            Route::post("/", [BookingController::class, "store"])->name("store");
            Route::post("{booking}/initiate-payment", [BookingController::class, "initiatePayment"])->name("initiate-payment");
            Route::post("{booking}/review", [SessionReviewController::class, "store"])->name("review");
            Route::get("{booking}/receipt", [SessionController::class, "receipt"])->name("receipt");
            Route::post("{booking}/cancel", [SessionController::class, "cancel"])->name("cancel");
            Route::post("{booking}/reschedule", [SessionController::class, "reschedule"])->name("reschedule");
            Route::get("{booking}/join", [SessionController::class, "join"])->name("join");
            Route::get("{booking}", [BookingController::class, "show"])->name("show");
        });

        Route::post("reschedules/{id}/respond", [SessionController::class, "respondToReschedule"])
            ->name("reschedules.respond");
    });

    Route::post("finance/payments/callback", [PaymentCallbackController::class, "callback"])
        ->middleware("auth:sanctum")
        ->name("finance.payments.callback");

    Route::prefix("therapist")->as("therapist.")->group(function () {
        Route::prefix("application")->as("application.")->group(function () {
            Route::get("/", [TherapistApplicationController::class, "show"])->name("show");
            Route::post("personal", [TherapistApplicationController::class, "personal"])->name("personal");
            Route::post("documents", [TherapistApplicationController::class, "storeDocument"])->name("documents.store");
            Route::delete("documents/{id}", [TherapistApplicationController::class, "deleteDocument"])->name("documents.delete");
            Route::post("specialties", [TherapistApplicationController::class, "specialties"])->name("specialties");
            Route::post("availability", [TherapistApplicationController::class, "availability"])->name("availability");
            Route::post("payout", [TherapistPayoutController::class, "payout"])->name("payout");
            Route::post("submit", [TherapistApplicationController::class, "submit"])->name("submit");
        });

        Route::get("banks", [TherapistPayoutController::class, "banks"])->name("banks");
        Route::post("payout-account/verify", [TherapistPayoutController::class, "verify"])->name("payout-account.verify");

        Route::prefix("sessions")->as("sessions.")->group(function () {
            Route::get("{session}/request", [SessionRequestController::class, "request"])->name("request");
            Route::post("{session}/acknowledge", [SessionRequestController::class, "acknowledge"])->name("acknowledge");
            Route::get("{session}/notes", [\App\Http\Controllers\Api\V2\Therapist\SessionNoteController::class, "show"])->name("notes.show");
            Route::post("{session}/notes", [\App\Http\Controllers\Api\V2\Therapist\SessionNoteController::class, "store"])->name("notes.store");
        });

        Route::get("sessions", [SessionRequestController::class, "index"])->name("sessions.index");

        Route::get("profile", [\App\Http\Controllers\Api\V2\Therapist\TherapistProfileController::class, "show"])->name("profile.show");
        Route::post("profile/update", [\App\Http\Controllers\Api\V2\Therapist\TherapistProfileController::class, "update"])->name("profile.update");

        Route::prefix("earnings")->as("earnings.")->group(function () {
            Route::get("dashboard", [\App\Http\Controllers\Api\V2\Therapist\EarningsController::class, "dashboard"])->name("dashboard");
            Route::get("transactions", [\App\Http\Controllers\Api\V2\Therapist\EarningsController::class, "transactions"])->name("transactions");
        });

        Route::prefix("payouts")->as("payouts.")->group(function () {
            Route::post("/", [\App\Http\Controllers\Api\V2\Therapist\PayoutController::class, "store"])->name("store");
            Route::get("{payout}", [\App\Http\Controllers\Api\V2\Therapist\PayoutController::class, "show"])->name("show");
        });

        Route::prefix("notes")->as("notes.")->group(function () {
            Route::get("/", [\App\Http\Controllers\Api\V2\Therapist\SessionNoteController::class, "index"])->name("index");
            Route::get("{note}", [\App\Http\Controllers\Api\V2\Therapist\SessionNoteController::class, "showById"])->name("show");
        });

        Route::prefix("clients")->as("clients.")->group(function () {
            Route::get("/", [\App\Http\Controllers\Api\V2\Therapist\ClientController::class, "index"])->name("index");
            Route::post("{user}/treatment-plan", [\App\Http\Controllers\Api\V2\Therapist\ClientController::class, "setTreatmentPlan"])->name("treatment-plan");
            Route::get("{user}", [\App\Http\Controllers\Api\V2\Therapist\ClientController::class, "show"])->name("show");
        });
    });
});

// AV provider call-state webhook (HMAC-verified, no session auth).
Route::post("webhooks/av-provider", [AvProviderWebhookController::class, "handle"])
    ->name("webhooks.av-provider");
