<?php

use App\Http\Controllers\Admin\ActivityLog\ActivityLogController;
use App\Http\Controllers\Admin\Announcement\AnnouncementController;
use App\Http\Controllers\Admin\Authorization\PermissionController;
use App\Http\Controllers\Admin\Authorization\RoleController;
use App\Http\Controllers\Admin\Avatar\AvatarController;
use App\Http\Controllers\Admin\BulkMessages\NotificationController as BulkMessagesNotificationController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Faq\FaqCategoryController;
use App\Http\Controllers\Admin\Faq\FaqController;
use App\Http\Controllers\Admin\Feedback\FeedbackController;
use App\Http\Controllers\Admin\Finance\Plan\PlanBenefitsController;
use App\Http\Controllers\Admin\Finance\Plan\PlanController;
use App\Http\Controllers\Admin\Finance\Promotion\PromotionController;
use App\Http\Controllers\Admin\Finance\Subscription\Flutterwave\SubscriptionController;
use App\Http\Controllers\Admin\Guideline\GuidelineController;
use App\Http\Controllers\Admin\Member\MemberController;
use App\Http\Controllers\Admin\Notification\NotificationController;
use App\Http\Controllers\Admin\PaymentGateways\Flutterwave\FlutterwaveController;
use App\Http\Controllers\Admin\Post\PostCategoryController;
use App\Http\Controllers\Admin\Profile\ProfileController;
use App\Http\Controllers\Admin\Report\CommentReportController;
use App\Http\Controllers\Admin\Report\GroupReportController;
use App\Http\Controllers\Admin\Report\PostReportController;
use App\Http\Controllers\Admin\User\AccountStatusController;
use App\Http\Controllers\Admin\User\UserController;
use App\Http\Controllers\Admin\Waitlist\WaitlistController;
use App\Http\Controllers\Admin\Web\PrivacyPolicyController;
use App\Http\Controllers\Admin\Web\TermAndConditionController;
use App\Http\Controllers\Web\InviteController;
use App\Models\Feedback;
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
            'guidelines' => GuidelineController::class,
            "terms-and-conditions" => TermAndConditionController::class,
            "privacy-policies" => PrivacyPolicyController::class,
            "faqs" => FaqController::class,
            "faq-categories" => FaqCategoryController::class,
            "feedbacks" => FeedbackController::class,
            'plans' => PlanController::class,
            'promotions' => PromotionController::class,
        ]);


        Route::prefix("users")->as("users.")->group(function () {
            Route::post('{id}/suspend', [UserController::class, "suspend"])->name("suspend");
            Route::post('{id}/strike', [UserController::class, "strike"])->name("strike");
            Route::post('{id}/ban', [UserController::class, "ban"])->name("ban");
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


        Route::prefix("plans/{plan}")->as("plans.")->group(function () {
            Route::resource('/plan-benefits', PlanBenefitsController::class);
        });

        Route::put('plan/cancel/{id}', [PlanController::class, 'cancelPlan'])->name('plan.cancel');
        
        Route::as("notifications.")->prefix("notifications")->group(function () {
            Route::get("clear-all", [NotificationController::class, "clearAll"])->name("clear-all");
            Route::get("mark-all", [NotificationController::class, "markAll"])->name("mark-all");
            Route::get("{notification}/delete", [NotificationController::class, "destroy"])->name("destroy");

            Route::resource('send-bulk-notification', BulkMessagesNotificationController::class);
            Route::post('send-bulk-notification/update-status/{id}', [BulkMessagesNotificationController::class, 'changeStatus'])->name('announcements.update-status');
        });

        Route::prefix("reports")->as("reports.")->group(function () {
            Route::get('post/lists', [PostReportController::class, "reportList"])->name("post.lists");
            Route::get('post/show/{id}', [PostReportController::class, "show"])->name("post.show");
            Route::post('post/update-status/{id}', [PostReportController::class, "updateStatus"])->name('post.update-status');
            Route::delete('post/delete/{id}', [PostReportController::class, "deleteReportedPost"])->name('post.delete');

            Route::get('group/lists', [GroupReportController::class, "reportList"])->name("group.lists");
            Route::get('group/show/{id}', [GroupReportController::class, "show"])->name("group.show");
            Route::post('group/activate/{id}', [GroupReportController::class, "activateReportedGroup"])->name("group.activate");
            Route::post('group/suspend/{id}', [GroupReportController::class, "suspendBanReportedGroup"])->name("group.suspend");
            Route::delete('group/delete/{id}', [GroupReportController::class, "deleteReportedGroup"])->name("group.delete");

            Route::get('group/member/lists', [GroupReportController::class, "groupReportList"])->name("group.member.lists");
            Route::post('group-member/suspend-or-ban/{id}', [GroupReportController::class, "suspendBanReportedGroupMember"])->name("group-member.suspend-or-ban");
            Route::post('group-member/undo-suspension/{id}', [GroupReportController::class, 'undoGroupMemberSuspension'])->name('group-member.undo-suspension');
            Route::get('group-member/show/{id}', [GroupReportController::class, 'showGroupMemberReport'])->name('group-member.show');
            // Route::post('group-member/ban/{id}', [GroupReportController::class, 'banGroupMember'])->name('group-member.ban');
            Route::post('group-member/suspend/{id}', [GroupReportController::class, "suspendReportedGroupMember"])->name("group-member.suspend");


            Route::get('comment/lists', [CommentReportController::class, "reportList"])->name("comment.lists");
            Route::get('comment/show/{id}', [CommentReportController::class, "show"])->name("comment.show");
            Route::delete('comment/delete/{id}', [CommentReportController::class, "deleteReportedComment"])->name('comment.delete');
            Route::post('comment/update-status/{id}', [CommentReportController::class, "updateStatus"])->name('comment.update-status');
        });

        Route::prefix("subscriptions")->as("subscriptions.")->group(function () {
            Route::post('flutterwave/{plan}/subscribe', [SubscriptionController::class, 'initiateSubscription'])->name('flutterwave.subscribe');
            // Route::get('flutterwave/callback', [FlutterwaveController::class, 'handleFlutterwavePaymentCallback'])->name('flutterwave.callback');            
        });
        Route::get('select-a-subscriber', [SubscriptionController::class, 'getSubscriber'])->name('select-a-subscriber');
        Route::resource('announcements', AnnouncementController::class);
        Route::post('announcements/update-status/{id}', [AnnouncementController::class, 'changeStatus'])->name('announcements.update-status');

        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::delete('activity-logs/{id}/destroy', [ActivityLogController::class, 'destroy'])->name('activity-logs.destroy');

        Route::put('feedback{id}/update-status', [FeedbackController::class, 'resolveFeedback'])->name('feedback.update-status');
        Route::post('feedback/respond/{id}', [FeedbackController::class, 'respondFeedback'])->name('feedback.respond');

        Route::prefix("waitlists")->as("waitlists.")->group(function () {
        Route::get('/index', [WaitlistController::class, 'index'])->name('index');
        Route::get('/export', [WaitlistController::class, 'export'])->name('export');
        Route::delete('destroy/{id}', [WaitlistController::class, 'destroy'])->name('destroy');
        });

        Route::get('promotions-items', [PromotionController::class, 'items'])->name('promotions-items');
        Route::get('promotions/{status}/get-by-status', [PromotionController::class, 'getBystatus'])->name('promotions.get-by-status');

    }
);
