<?php

use App\Models\Course;
use App\Models\Payout;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\Route;

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
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Content\CourseController;
use App\Http\Controllers\Content\LessonController;
use App\Http\Controllers\Content\LessonComponentController;
use App\Http\Controllers\Learning\AssessmentController;
use App\Http\Controllers\Learning\EnrollmentController;
use App\Http\Controllers\Learning\PathController;
use App\Http\Controllers\Learning\LessonPlayController;
use App\Http\Controllers\Learning\ProgressController;
use App\Http\Controllers\Learning\AnswerController;
use App\Http\Controllers\Learning\SpeakingSubmissionController;
use App\Http\Controllers\Gamification\StreakController;
use App\Http\Controllers\Gamification\HeartController;
use App\Http\Controllers\Gamification\LeaderboardController;
use App\Http\Controllers\Family\FamilyController;
use App\Http\Controllers\Family\WalletController;
use App\Http\Controllers\Family\ChoreController;
use App\Http\Controllers\Family\ReviewController;
use App\Http\Controllers\Billing\PlanController;
use App\Http\Controllers\Billing\SubscriptionController;
use App\Http\Controllers\Billing\TelcoController;
use App\Http\Controllers\Billing\DataBundleController;
use App\Http\Controllers\Billing\InvoiceController;
use App\Http\Controllers\Referral\ReferralController;
use App\Http\Controllers\Referral\PayoutController;
use App\Http\Controllers\School\SchoolDashboardController;
use App\Http\Controllers\School\RosterController;
use App\Http\Controllers\School\SchoolClassController;
use App\Http\Controllers\School\SeatController;
use App\Http\Controllers\Admin\AdminMetricsController;
use App\Http\Controllers\Admin\SettlementController;
use App\Http\Controllers\Admin\PromoCodeController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use App\Http\Controllers\Webhooks\TelcoWebhookController;

Route::prefix('v1')->group(function () {

    /* ---------------------------------------------------------------- public */
    Route::get('config', [ConfigController::class, 'show']);

    Route::middleware('throttle:auth')->group(function () {
        Route::post('auth/register', [AuthController::class, 'register']);
        Route::post('auth/login',    [AuthController::class, 'login']);
        Route::post('auth/google',   [AuthController::class, 'google']);
        Route::post('auth/password/forgot', [PasswordController::class, 'forgot']);
        Route::post('auth/password/reset',  [PasswordController::class, 'reset']);
    });

    // Inbound webhooks — signature-verified + idempotent inside the controller.
    Route::post('webhooks/paystack',    [PaymentWebhookController::class, 'paystack']);
    Route::post('webhooks/flutterwave', [PaymentWebhookController::class, 'flutterwave']);
    Route::post('webhooks/telco/dlr',   [TelcoWebhookController::class, 'dlr']);

    /* ------------------------------------------------------------- protected */
    Route::middleware(['auth:sanctum', 'identify.tenant', 'min.app.version'])->group(function () {

        Route::get('me', [MeController::class, 'show']);
        Route::post('me/devices', [DeviceController::class, 'store']);
        Route::post('auth/refresh', [AuthController::class, 'refresh']);
        Route::delete('auth/token', [AuthController::class, 'logout']);
        Route::post('profiles/{learner}/switch', [ProfileController::class, 'switch'])
            ->can('view', 'learner');

        /* ---- Content / CMS (content_owner) ---- */
        Route::get('courses', [CourseController::class, 'index']);
        Route::get('courses/{course}', [CourseController::class, 'show'])->can('view', 'course');
        Route::post('courses', [CourseController::class, 'store'])->can('create', Course::class);
        Route::match(['put', 'patch'], 'courses/{course}', [CourseController::class, 'update'])->can('update', 'course');
        Route::delete('courses/{course}', [CourseController::class, 'destroy'])->can('delete', 'course');

        Route::post('lessons/{lesson}/components', [LessonComponentController::class, 'store'])
            ->middleware('can:content.lessons.manage');
        Route::post('lessons/{lesson}/publish', [LessonController::class, 'publish'])
            ->middleware('can:content.courses.publish');

        /* ---- Learning loop (learner / parent) ---- */
        Route::post('assessments', [AssessmentController::class, 'store']);
        Route::post('enrollments', [EnrollmentController::class, 'store'])
            ->middleware('can:learning.enrollments.manage');
        Route::get('learners/{learner}/path', [PathController::class, 'show'])->can('view', 'learner');
        Route::get('learners/{learner}/progress', [ProgressController::class, 'show'])->can('view', 'learner');
        Route::get('lessons/{lesson}/play', [LessonPlayController::class, 'show']);
        Route::post('lessons/{lesson}/progress', [ProgressController::class, 'store']);
        Route::post('components/{component}/answer', [AnswerController::class, 'store']);
        Route::post('lessons/{lesson}/complete', [LessonController::class, 'complete']);
        Route::post('speaking-submissions', [SpeakingSubmissionController::class, 'store']);

        /* ---- Gamification ---- */
        Route::get('learners/{learner}/streak', [StreakController::class, 'show'])->can('view', 'learner');
        Route::post('streak/shield', [StreakController::class, 'shield']);
        Route::get('hearts', [HeartController::class, 'show']);
        Route::post('hearts/refill', [HeartController::class, 'refill']);
        Route::get('leagues/current', [LeaderboardController::class, 'current']);
        Route::get('leaderboard', [LeaderboardController::class, 'index']);

        /* ---- Family & wallet (parent) ---- */
        Route::get('family', [FamilyController::class, 'show'])->middleware('can:family.manage');
        Route::post('family/children', [FamilyController::class, 'addChild'])->middleware('can:family.manage');
        Route::get('wallet', [WalletController::class, 'show'])->middleware('can:family.wallet.view');
        Route::get('chores', [ChoreController::class, 'index'])->middleware('can:family.chores.manage');
        Route::post('chores', [ChoreController::class, 'store'])->middleware('can:family.chores.manage');
        Route::post('chores/{chore}/review', [ChoreController::class, 'review'])->middleware('can:family.chores.review');
        Route::get('reviews/pending', [ReviewController::class, 'pending'])->middleware('can:family.reviews.handle');

        // money POSTs → idempotent
        Route::middleware('idempotency')->group(function () {
            Route::post('wallet/fund', [WalletController::class, 'fund'])->middleware('can:family.wallet.fund');
            Route::post('wallet/transfer', [WalletController::class, 'transfer'])->middleware('can:family.wallet.fund');
        });

        /* ---- Billing ---- */
        Route::get('plans', [PlanController::class, 'index']);
        Route::get('telco/status', [TelcoController::class, 'status'])->middleware('can:billing.telco.view');
        Route::post('telco/subscribe', [TelcoController::class, 'subscribe'])->middleware('can:billing.telco.manage');
        Route::post('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])
            ->middleware('can:billing.subscriptions.manage');
        Route::get('data-bundles', [DataBundleController::class, 'index']);

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
        });
        Route::get('classes', [SchoolClassController::class, 'index'])->can('viewAny', SchoolClass::class);
        Route::get('classes/{class}', [SchoolClassController::class, 'show'])->can('view', 'class');
        Route::post('classes', [SchoolClassController::class, 'store'])->can('create', SchoolClass::class);
        Route::match(['put', 'patch'], 'classes/{class}', [SchoolClassController::class, 'update'])->can('update', 'class');

        /* ---- Super Admin (global, unscoped) ---- */
        Route::prefix('admin')->group(function () {
            Route::get('metrics', [AdminMetricsController::class, 'index'])->middleware('can:analytics.platform.view');
            Route::get('billing/health', [AdminMetricsController::class, 'billingHealth'])->middleware('can:billing.health.view');
            Route::get('settlements', [SettlementController::class, 'index'])->middleware('can:settlements.view');
            Route::post('payouts/{payout}/approve', [PayoutController::class, 'approve'])->can('approve', 'payout');
            Route::post('promo-codes', [PromoCodeController::class, 'store'])->middleware('can:promocodes.manage');
            Route::get('organizations', [OrganizationController::class, 'index'])->middleware('can:organizations.view');
            Route::post('organizations/{organization}/activate', [OrganizationController::class, 'activate'])
                ->middleware('can:organizations.activate');
        });
    });
});
