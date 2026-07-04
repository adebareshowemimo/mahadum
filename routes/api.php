<?php

use App\Http\Controllers\Admin\AdminMetricsController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\CompetitionAdminController;
use App\Http\Controllers\Admin\ContactListController;
use App\Http\Controllers\Admin\EmailCampaignController;
use App\Http\Controllers\Admin\EmailLogController;
use App\Http\Controllers\Admin\FraudController;
use App\Http\Controllers\Admin\GatewayController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Admin\PromoCodeController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SettlementController;
use App\Http\Controllers\Admin\SupportController;
use App\Http\Controllers\Admin\UserController;
/*
|--------------------------------------------------------------------------
| Mahadum.360 API (v1)
|--------------------------------------------------------------------------
| Auth = Sanctum (cookie for web SPA, bearer for mobile).
| Middleware chain on protected routes: auth:sanctum → identify.tenant → can:* .
|
| Two guard styles are used below:
|   • can:<permission>            → coarse capability (spatie permission string),
|                                   evaluated for the ACTIVE org (teams scope).
|   • can:<ability>,<binding>     → policy check w/ role + tenant ownership.
|
| Controllers referenced here are the "next layer" — generate them to match.
| Idempotency is required on money POSTs (wallet, subscriptions, payouts).
*/

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Billing\DataBundleController;
use App\Http\Controllers\Billing\InvoiceController;
use App\Http\Controllers\Billing\PlanController;
use App\Http\Controllers\Billing\SubscriptionController;
use App\Http\Controllers\Billing\TelcoController;
use App\Http\Controllers\Competition\CompetitionController;
use App\Http\Controllers\Competition\EntryController as CompetitionEntryController;
use App\Http\Controllers\Competition\VoteController as CompetitionVoteController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\Content\CourseController;
use App\Http\Controllers\Content\CourseLevelController;
use App\Http\Controllers\Content\LessonAnalyticsController;
use App\Http\Controllers\Content\LessonComponentController;
use App\Http\Controllers\Content\LessonController;
use App\Http\Controllers\Content\MediaController;
use App\Http\Controllers\Content\QuizImportController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\Family\ChoreController;
use App\Http\Controllers\Family\FamilyController;
use App\Http\Controllers\Family\ReviewController;
use App\Http\Controllers\Family\WalletController;
use App\Http\Controllers\Gamification\BadgeController;
use App\Http\Controllers\Gamification\HeartController;
use App\Http\Controllers\Gamification\LeaderboardController;
use App\Http\Controllers\Gamification\StreakController;
use App\Http\Controllers\Learning\AnswerController;
use App\Http\Controllers\Learning\AssessmentController;
use App\Http\Controllers\Learning\AssignmentSubmissionController;
use App\Http\Controllers\Learning\EnrollmentController;
use App\Http\Controllers\Learning\LessonCompletionController;
use App\Http\Controllers\Learning\LessonPlayController;
use App\Http\Controllers\Learning\PathController;
use App\Http\Controllers\Learning\ProgressController;
use App\Http\Controllers\Learning\SpeakingSubmissionController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Referral\PayoutController;
use App\Http\Controllers\Referral\ReferralController;
use App\Http\Controllers\School\RosterController;
use App\Http\Controllers\School\SchoolClassController;
use App\Http\Controllers\School\SchoolDashboardController;
use App\Http\Controllers\School\SeatController;
use App\Http\Controllers\Support\TicketController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use App\Http\Controllers\Webhooks\SendgridWebhookController;
use App\Http\Controllers\Webhooks\TelcoWebhookController;
use App\Models\Course;
use App\Models\Payout;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    /* ---------------------------------------------------------------- public */
    Route::get('config', [ConfigController::class, 'show']);
    Route::get('pricing', [PricingController::class, 'index']);

    Route::middleware('throttle:auth')->group(function () {
        Route::post('auth/register', [AuthController::class, 'register']);
        Route::post('auth/login', [AuthController::class, 'login']);
        Route::post('auth/google', [AuthController::class, 'google']);
        Route::post('auth/password/forgot', [PasswordController::class, 'forgot']);
        Route::post('auth/password/reset', [PasswordController::class, 'reset']);
    });

    // Email verification link (clicked from the inbox; proven by the signature
    // + hash, so no bearer token is required).
    Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // Inbound webhooks — signature-verified + idempotent inside the controller.
    Route::post('webhooks/paystack', [PaymentWebhookController::class, 'paystack']);
    Route::post('webhooks/flutterwave', [PaymentWebhookController::class, 'flutterwave']);
    Route::post('webhooks/monnify', [PaymentWebhookController::class, 'monnify']);
    Route::post('webhooks/telco/dlr', [TelcoWebhookController::class, 'dlr']);
    // SendGrid Event Webhook (token in the URL) — bounces/complaints → suppression.
    Route::post('webhooks/sendgrid/{token}', [SendgridWebhookController::class, 'handle']);

    /* ------------------------------------------------------------- protected */
    Route::middleware(['auth:sanctum', 'identify.tenant', 'min.app.version'])->group(function () {

        Route::get('me', [MeController::class, 'show']);
        Route::post('me/devices', [DeviceController::class, 'store']);
        Route::post('auth/refresh', [AuthController::class, 'refresh']);
        Route::delete('auth/token', [AuthController::class, 'logout']);
        Route::post('profiles/{learner}/switch', [ProfileController::class, 'switch'])
            ->can('view', 'learner');

        // In-app notifications (database channel).
        Route::get('me/notifications', [NotificationController::class, 'index']);
        Route::post('me/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('me/notifications/{id}/read', [NotificationController::class, 'markRead']);

        // Email verification (resend; the verify link itself is public + signed).
        Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:6,1');

        /* ---- Content / CMS (content_owner) ---- */
        Route::get('courses', [CourseController::class, 'index']);
        Route::get('courses/{course}', [CourseController::class, 'show'])->can('view', 'course');
        Route::post('courses', [CourseController::class, 'store'])->can('create', Course::class);
        Route::match(['put', 'patch'], 'courses/{course}', [CourseController::class, 'update'])->can('update', 'course');
        Route::delete('courses/{course}', [CourseController::class, 'destroy'])->can('delete', 'course');
        Route::post('courses/{course}/publish', [CourseController::class, 'publish'])->middleware('can:content.courses.publish');
        Route::post('courses/{course}/unpublish', [CourseController::class, 'unpublish'])->middleware('can:content.courses.publish');

        // course structure: levels → lessons → components
        Route::get('courses/{course}/levels', [CourseController::class, 'levels']);
        Route::post('courses/{course}/levels', [CourseLevelController::class, 'store'])
            ->middleware('can:content.lessons.manage');
        Route::get('levels/{level}/lessons', [LessonController::class, 'index']);
        Route::post('levels/{level}/lessons', [LessonController::class, 'store'])
            ->middleware('can:content.lessons.manage');
        Route::get('lessons/{lesson}', [LessonController::class, 'show']);

        Route::post('quiz-imports/parse', [QuizImportController::class, 'parse'])
            ->middleware('can:content.lessons.manage');
        Route::post('lessons/{lesson}/components', [LessonComponentController::class, 'store'])
            ->middleware('can:content.lessons.manage');
        Route::match(['put', 'patch'], 'components/{component}', [LessonComponentController::class, 'update'])
            ->middleware('can:content.lessons.manage');
        Route::delete('components/{component}', [LessonComponentController::class, 'destroy'])
            ->middleware('can:content.lessons.manage');
        Route::post('lessons/{lesson}/publish', [LessonController::class, 'publish'])
            ->middleware('can:content.courses.publish');
        Route::get('lessons/{lesson}/analytics', [LessonAnalyticsController::class, 'show'])
            ->middleware('can:analytics.lesson.view');

        // Simple local-disk media upload (video/audio/image) → MediaAsset.
        Route::get('media', [MediaController::class, 'index'])
            ->middleware('can:content.media.upload');
        Route::get('media/orphans', [MediaController::class, 'orphans'])
            ->middleware('can:content.media.upload');
        Route::post('media/orphans/purge', [MediaController::class, 'purgeOrphans'])
            ->middleware('can:content.media.upload');
        Route::post('media/upload', [MediaController::class, 'upload'])
            ->middleware('can:content.media.upload');
        Route::delete('media/{asset}', [MediaController::class, 'destroy'])
            ->middleware('can:content.media.upload');

        /* ---- Learning loop (learner / parent) ---- */
        Route::post('assessments', [AssessmentController::class, 'store']);
        Route::post('enrollments', [EnrollmentController::class, 'store'])
            ->middleware('can:learning.enrollments.manage');
        Route::get('learners/{learner}/path', [PathController::class, 'show'])->can('view', 'learner');
        Route::get('learners/{learner}/progress', [ProgressController::class, 'show'])->can('view', 'learner');
        Route::get('lessons/{lesson}/play', [LessonPlayController::class, 'show']);
        Route::post('lessons/{lesson}/progress', [ProgressController::class, 'store']);
        Route::post('components/{component}/answer', [AnswerController::class, 'store']);
        Route::post('lessons/{lesson}/complete', [LessonCompletionController::class, 'complete']);
        Route::post('speaking-submissions', [SpeakingSubmissionController::class, 'store']);
        Route::post('assignment-submissions', [AssignmentSubmissionController::class, 'store']);

        /* ---- Gamification ---- */
        Route::get('learners/{learner}/streak', [StreakController::class, 'show'])->can('view', 'learner');
        Route::post('streak/shield', [StreakController::class, 'shield']);
        Route::get('hearts', [HeartController::class, 'show']);
        Route::post('hearts/refill', [HeartController::class, 'refill']);
        Route::get('leagues/current', [LeaderboardController::class, 'current']);
        Route::get('leaderboard', [LeaderboardController::class, 'index']);
        Route::get('learners/{learner}/badges', [BadgeController::class, 'index'])->can('view', 'learner');

        /* ---- Family & wallet (parent) ---- */
        Route::get('family', [FamilyController::class, 'show'])->middleware('can:family.manage');
        Route::post('family/children', [FamilyController::class, 'addChild'])->middleware('can:family.manage');
        Route::put('family/pin', [FamilyController::class, 'setPin'])->middleware('can:family.manage');
        Route::get('wallet', [WalletController::class, 'show'])->middleware('can:family.wallet.view');
        Route::get('chores', [ChoreController::class, 'index'])->middleware('can:family.chores.manage');
        Route::post('chores', [ChoreController::class, 'store'])->middleware('can:family.chores.manage');
        Route::post('chores/{chore}/review', [ChoreController::class, 'review'])->middleware('can:family.chores.review');
        Route::get('reviews/pending', [ReviewController::class, 'pending'])->middleware('can:family.reviews.handle');
        Route::post('assignment-submissions/{submission}/review', [ReviewController::class, 'review'])->middleware('can:family.reviews.handle');

        // money POSTs → idempotent
        Route::middleware('idempotency')->group(function () {
            Route::post('wallet/fund', [WalletController::class, 'fund'])->middleware('can:family.wallet.fund');
            Route::post('wallet/transfer', [WalletController::class, 'transfer'])->middleware('can:family.wallet.fund');
        });

        /* ---- Billing ---- */
        Route::get('plans', [PlanController::class, 'index']);
        Route::get('subscriptions', [SubscriptionController::class, 'index'])
            ->middleware('can:billing.subscriptions.manage');
        Route::get('telco/status', [TelcoController::class, 'status'])->middleware('can:billing.telco.view');
        Route::post('telco/otp/request', [TelcoController::class, 'requestOtp'])
            ->middleware(['can:billing.telco.manage', 'throttle:6,1']);
        Route::post('telco/otp/verify', [TelcoController::class, 'verifyOtp'])
            ->middleware(['can:billing.telco.manage', 'throttle:10,1']);
        Route::post('telco/subscribe', [TelcoController::class, 'subscribe'])->middleware('can:billing.telco.manage');
        Route::post('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])
            ->middleware('can:billing.subscriptions.manage');
        Route::get('data-bundles', [DataBundleController::class, 'index']);

        // Preview a promo code against a plan (no side effects) before checkout.
        Route::post('subscriptions/promo-preview', [SubscriptionController::class, 'promoPreview'])
            ->middleware('can:billing.subscriptions.manage');

        Route::middleware('idempotency')->group(function () {
            Route::post('subscriptions', [SubscriptionController::class, 'store'])
                ->middleware('can:billing.subscriptions.manage');
            Route::post('data-bundles/purchase', [DataBundleController::class, 'purchase'])
                ->middleware('can:billing.databundles.manage');
        });

        /* ---- Referrals & payouts ---- */
        Route::get('referral-code', [ReferralController::class, 'code'])->middleware('can:referrals.view');
        Route::get('referrals/summary', [ReferralController::class, 'summary'])->middleware('can:referrals.view');
        Route::get('payouts', [PayoutController::class, 'index'])->can('viewAny', Payout::class);
        Route::middleware('idempotency')->group(function () {
            Route::post('payouts/request', [PayoutController::class, 'store'])->can('create', Payout::class);
        });

        /* ---- School operations (org-scoped) ---- */
        Route::prefix('schools/{organization}')->group(function () {
            Route::get('dashboard', [SchoolDashboardController::class, 'show'])->middleware('can:schools.dashboard.view');
            Route::post('students/import', [RosterController::class, 'import'])->middleware('can:schools.roster.import');
            Route::get('seats', [SeatController::class, 'index'])->middleware('can:schools.seats.view');
            Route::post('seats/purchase', [SeatController::class, 'purchase'])->middleware('can:schools.seats.purchase');
            Route::get('invoices', [InvoiceController::class, 'index'])->middleware('can:billing.invoices.view');
            Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'download'])->middleware('can:billing.invoices.view');
        });
        Route::get('classes', [SchoolClassController::class, 'index'])->can('viewAny', SchoolClass::class);
        Route::get('classes/{class}', [SchoolClassController::class, 'show'])->can('view', 'class');
        Route::get('classes/{class}/analytics', [SchoolClassController::class, 'analytics'])->can('view', 'class');
        Route::post('classes', [SchoolClassController::class, 'store'])->can('create', SchoolClass::class);
        Route::match(['put', 'patch'], 'classes/{class}', [SchoolClassController::class, 'update'])->can('update', 'class');

        /* ---- Language & Culture competition ---- */
        // Browsing + voting are open to any signed-in user; entering is permissioned.
        Route::get('competitions', [CompetitionController::class, 'index']);
        Route::get('competitions/mine', [CompetitionEntryController::class, 'mine']);
        Route::get('competitions/{competition}', [CompetitionController::class, 'show']);
        Route::post('competitions/{competition}/entries', [CompetitionEntryController::class, 'store'])
            ->middleware('can:competitions.enter');
        Route::post('competitions/{competition}/entries/{entry}/vote', [CompetitionVoteController::class, 'store']);

        /* ---- Support (any authenticated user) ---- */
        Route::get('support/tickets', [TicketController::class, 'index']);
        Route::post('support/tickets', [TicketController::class, 'store']);
        Route::post('support/tickets/{ticket}/messages', [TicketController::class, 'reply']);

        /* ---- Super Admin (global, unscoped) ---- */
        Route::prefix('admin')->group(function () {
            Route::get('metrics', [AdminMetricsController::class, 'index'])->middleware('can:analytics.platform.view');
            Route::get('billing/health', [AdminMetricsController::class, 'billingHealth'])->middleware('can:billing.health.view');
            Route::get('settlements', [SettlementController::class, 'index'])->middleware('can:settlements.view');
            Route::get('payouts', [PayoutController::class, 'adminIndex'])->middleware('can:payouts.view');
            Route::post('payouts/{payout}/approve', [PayoutController::class, 'approve'])->can('approve', 'payout');
            Route::post('payouts/{payout}/reject', [PayoutController::class, 'reject'])->middleware('can:payouts.approve');
            Route::post('promo-codes', [PromoCodeController::class, 'store'])->middleware('can:promocodes.manage');
            Route::get('organizations', [OrganizationController::class, 'index'])->middleware('can:organizations.view');
            Route::get('organizations/{organization}', [OrganizationController::class, 'show'])->middleware('can:organizations.view');
            Route::post('organizations', [OrganizationController::class, 'store'])->middleware('can:organizations.manage');
            Route::post('organizations/{organization}/invite-admin', [OrganizationController::class, 'inviteAdmin'])
                ->middleware('can:organizations.manage');
            Route::match(['put', 'patch'], 'organizations/{organization}', [OrganizationController::class, 'update'])
                ->middleware('can:organizations.manage');
            Route::post('organizations/{organization}/activate', [OrganizationController::class, 'activate'])
                ->middleware('can:organizations.activate');
            Route::post('organizations/{organization}/status', [OrganizationController::class, 'setStatus'])
                ->middleware('can:organizations.manage');

            // Users & access control
            Route::get('users', [UserController::class, 'index'])->middleware('can:users.view');
            Route::post('users/{user}/roles', [UserController::class, 'assignRole'])->middleware('can:roles.assign');
            Route::post('users/{user}/status', [UserController::class, 'setStatus'])->middleware('can:users.manage');
            Route::get('roles', [RoleController::class, 'index'])->middleware('can:roles.view');
            Route::get('audit-logs', [AuditController::class, 'index'])->middleware('can:audit.view');

            // Support triage
            Route::get('support-tickets', [SupportController::class, 'index'])->middleware('can:support.handle');
            Route::match(['put', 'patch'], 'support-tickets/{ticket}', [SupportController::class, 'update'])->middleware('can:support.handle');
            Route::post('support-tickets/{ticket}/messages', [SupportController::class, 'addMessage'])->middleware('can:support.handle');

            // Subscription plan pricing
            Route::get('plans', [AdminPlanController::class, 'index'])->middleware('can:billing.plans.manage');
            Route::post('plans', [AdminPlanController::class, 'store'])->middleware('can:billing.plans.manage');
            Route::match(['put', 'patch'], 'plans/{plan}', [AdminPlanController::class, 'update'])->middleware('can:billing.plans.manage');

            // Language & Culture competition (organise / moderate / judge)
            Route::get('competitions', [CompetitionAdminController::class, 'index'])->middleware('can:competitions.manage');
            Route::get('competitions/{competition}', [CompetitionAdminController::class, 'show'])->middleware('can:competitions.manage');
            Route::post('competitions', [CompetitionAdminController::class, 'store'])->middleware('can:competitions.manage');
            Route::match(['put', 'patch'], 'competitions/{competition}', [CompetitionAdminController::class, 'update'])->middleware('can:competitions.manage');
            Route::post('competitions/{competition}/status', [CompetitionAdminController::class, 'setStatus'])->middleware('can:competitions.manage');
            Route::post('competitions/{competition}/entries/{entry}/moderate', [CompetitionAdminController::class, 'moderateEntry'])->middleware('can:competitions.manage');
            Route::post('competitions/{competition}/judge', [CompetitionAdminController::class, 'judge'])->middleware('can:competitions.manage');

            // Language activation
            Route::get('languages', [LanguageController::class, 'index'])->middleware('can:content.languages.manage');
            Route::post('languages/reorder', [LanguageController::class, 'reorder'])->middleware('can:content.languages.manage');
            Route::match(['put', 'patch'], 'languages/{language}', [LanguageController::class, 'update'])->middleware('can:content.languages.manage');

            // Referral fraud review (FR-7.5 velocity flags)
            Route::get('referrals/flagged', [FraudController::class, 'index'])->middleware('can:referrals.fraud.review');
            Route::post('referrals/{referralCode}/clear', [FraudController::class, 'clear'])->middleware('can:referrals.fraud.review');
            Route::post('referrals/{referralCode}/freeze', [FraudController::class, 'freeze'])->middleware('can:referrals.fraud.review');

            // System settings (DB-backed overrides of config defaults)
            Route::get('settings', [SettingsController::class, 'index'])->middleware('can:system.settings.manage');
            Route::match(['put', 'patch'], 'settings', [SettingsController::class, 'update'])->middleware('can:system.settings.manage');

            // Reports
            Route::get('reports/income', [ReportController::class, 'income'])->middleware('can:analytics.platform.view');
            Route::get('reports/growth', [ReportController::class, 'growth'])->middleware('can:analytics.platform.view');
            Route::get('reports/subscriptions', [ReportController::class, 'subscriptions'])->middleware('can:analytics.platform.view');
            Route::get('reports/referrals', [ReportController::class, 'referrals'])->middleware('can:analytics.platform.view');
            Route::get('reports/org-activity', [ReportController::class, 'orgActivity'])->middleware('can:analytics.platform.view');
            Route::get('reports/renewals', [ReportController::class, 'renewals'])->middleware('can:analytics.platform.view');

            // Email — campaigns (compose / target / send / schedule)
            Route::get('email-campaigns', [EmailCampaignController::class, 'index'])->middleware('can:emails.campaigns.manage');
            Route::post('email-campaigns', [EmailCampaignController::class, 'store'])->middleware('can:emails.campaigns.manage');
            Route::get('email-campaigns/{emailCampaign}', [EmailCampaignController::class, 'show'])->middleware('can:emails.campaigns.manage');
            Route::post('email-campaigns/{emailCampaign}/test', [EmailCampaignController::class, 'test'])->middleware('can:emails.campaigns.manage');
            Route::post('email-campaigns/{emailCampaign}/send', [EmailCampaignController::class, 'send'])->middleware('can:emails.campaigns.manage');
            Route::post('email-campaigns/{emailCampaign}/cancel', [EmailCampaignController::class, 'cancel'])->middleware('can:emails.campaigns.manage');
            Route::get('email-campaigns/{emailCampaign}/recipients', [EmailCampaignController::class, 'recipients'])->middleware('can:emails.campaigns.manage');

            // Email — log of every outbound message (§7)
            Route::get('email-log', [EmailLogController::class, 'index'])->middleware('can:emails.log.view');

            // Email — contact lists + upload pipeline (super-admin-only)
            Route::get('contact-lists', [ContactListController::class, 'index'])->middleware('can:emails.contacts.manage');
            Route::post('contact-lists', [ContactListController::class, 'store'])->middleware('can:emails.contacts.manage');
            Route::get('contact-lists/{contactList}', [ContactListController::class, 'show'])->middleware('can:emails.contacts.manage');
            Route::post('contact-lists/{contactList}/import/preview', [ContactListController::class, 'previewImport'])->middleware('can:emails.contacts.manage');
            Route::post('contact-lists/{contactList}/import', [ContactListController::class, 'import'])->middleware('can:emails.contacts.manage');
            Route::post('contact-lists/{contactList}/contacts', [ContactListController::class, 'storeContact'])->middleware('can:emails.contacts.manage');
            Route::match(['put', 'patch'], 'contact-lists/{contactList}/contacts/{contact}', [ContactListController::class, 'updateContact'])->middleware('can:emails.contacts.manage');
            Route::delete('contact-lists/{contactList}/contacts/{contact}', [ContactListController::class, 'destroyContact'])->middleware('can:emails.contacts.manage');

            // Payment gateway console (env-configured; secrets never leave the server)
            Route::get('payment-gateways', [GatewayController::class, 'index'])->middleware('can:system.settings.manage');
            Route::post('payment-gateways/{provider}/test', [GatewayController::class, 'test'])->middleware('can:system.settings.manage');
        });
    });
});
