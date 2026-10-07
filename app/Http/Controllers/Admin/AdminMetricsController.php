<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Language;
use App\Models\LessonProgress;
use App\Models\Organization;
use App\Models\QuizAttempt;
use App\Models\SpeakingSubmission;
use App\Models\Subscription;
use App\Models\SubscriptionPaymentReceipt;
use App\Models\TelcoBillingAttempt;
use App\Models\User;
use App\Models\WalletFundingTransaction;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;

class AdminMetricsController extends Controller
{
    /** Platform-wide KPIs (super_admin, unscoped). */
    public function index(Settings $settings): JsonResponse
    {
        $receipts = [
            'wallet_funding_minor' => (int) WalletFundingTransaction::where('status', 'success')->where('currency', 'NGN')->sum('amount_minor'),
            'school_invoices_minor' => (int) Invoice::where('status', 'paid')->sum('amount_minor'),
            'telco_minor' => (int) TelcoBillingAttempt::where('result', 'success')->sum('amount_minor'),
            'subscription_payments_minor' => (int) SubscriptionPaymentReceipt::where('currency', 'NGN')
                ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'payment' THEN amount_minor ELSE -amount_minor END), 0) as total")->value('total'),
        ];
        $enrolled = Enrollment::join('courses', 'courses.id', '=', 'enrollments.course_id')
            ->selectRaw('courses.language_id, COUNT(DISTINCT enrollments.learner_profile_id) as learners')->groupBy('courses.language_id')->get()->keyBy('language_id');
        $progress = LessonProgress::join('lessons', 'lessons.id', '=', 'lesson_progress.lesson_id')
            ->join('course_levels', 'course_levels.id', '=', 'lessons.course_level_id')->join('courses', 'courses.id', '=', 'course_levels.course_id')
            ->where('lesson_progress.status', 'completed')->selectRaw('courses.language_id, COUNT(*) as completed')->groupBy('courses.language_id')->get()->keyBy('language_id');
        $quiz = QuizAttempt::join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->join('lesson_components', 'lesson_components.id', '=', 'quizzes.lesson_component_id')
            ->join('lessons', 'lessons.id', '=', 'lesson_components.lesson_id')
            ->join('course_levels', 'course_levels.id', '=', 'lessons.course_level_id')->join('courses', 'courses.id', '=', 'course_levels.course_id')
            ->whereNotNull('quiz_attempts.completed_at')->whereNotNull('quiz_attempts.score')
            ->selectRaw('courses.language_id, COUNT(*) as scored, AVG(quiz_attempts.score) as average')->groupBy('courses.language_id')->get()->keyBy('language_id');
        $aiScored = SpeakingSubmission::whereNotNull('ai_score');
        $aiAverage = $aiScored->avg('ai_score');

        return response()->json(['data' => [
            'users' => User::count(),
            'users_by_type' => [
                'school' => User::whereHas('organizations')->count(),
                'family' => User::whereDoesntHave('organizations')->whereHas('ownedFamilies')->count(),
                'single' => User::whereDoesntHave('organizations')->whereDoesntHave('ownedFamilies')->count(),
            ],
            'organizations' => Organization::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
            'subscriptions' => Subscription::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
            'revenue_minor' => array_sum($receipts),
            'revenue_channels' => $receipts,
            'languages' => Language::where('is_active', true)->count(),
            'language_analytics' => Language::orderBy('position')->orderBy('id')->get()->map(fn ($language) => [
                'id' => $language->id, 'name' => $language->name, 'active' => $language->is_active,
                'learners' => (int) ($enrolled[$language->id]->learners ?? 0),
                'lessons_completed' => (int) ($progress[$language->id]->completed ?? 0),
                'quizzes_scored' => (int) ($quiz[$language->id]->scored ?? 0),
                'avg_quiz_score' => isset($quiz[$language->id]) ? round((float) $quiz[$language->id]->getAttribute('average') * 100, 1) : null,
            ]),
            'ai_analytics' => [
                'enabled' => (bool) $settings->get('feature.ai_pronunciation'),
                'scoring_status' => 'deferred',
                'submissions' => SpeakingSubmission::count(), 'scored' => $aiScored->count(),
                'needs_review' => SpeakingSubmission::where('status', 'needs_review')->count(),
                'avg_stored_score' => $aiAverage === null ? null : round((float) $aiAverage, 2),
            ],
        ]]);
    }

    public function billingHealth(): JsonResponse
    {
        $attempts = TelcoBillingAttempt::count();
        $success = TelcoBillingAttempt::where('result', 'success')->count();

        return response()->json(['data' => [
            'telco' => [
                'attempts' => $attempts,
                'success' => $success,
                'success_rate' => $attempts > 0 ? round($success / $attempts, 4) : null,
            ],
            'funding' => WalletFundingTransaction::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
            'subscriptions' => Subscription::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
        ]]);
    }
}
