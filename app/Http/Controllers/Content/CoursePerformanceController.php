<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonComponent;
use App\Models\LessonProgress;
use App\Models\Question;
use App\Models\QuestionResponse;
use App\Models\Referral;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "How is my content performing — and earning" for the content_owner role.
 *
 * MAHADUM.360 sells platform subscriptions, not individual courses, so no
 * per-course purchase record exists. Rather than report nothing, revenue is
 * **attributed**: each subscriber's plan price is split evenly across the
 * distinct courses their learners are enrolled in platform-wide, and each
 * course claims its share. A parent on a ₦6,000 plan whose children are
 * enrolled in 3 courses contributes ₦2,000 to each. This is an estimate, not a
 * billing figure — the UI labels it as such, and platform-wide actual revenue
 * stays super_admin-only (`analytics.platform.view`, Roles_Permissions.md §4).
 *
 * Scoped to courses the caller owns; super_admin sees every course via
 * Gate::before.
 */
class CoursePerformanceController extends Controller
{
    /** Commission statuses that represent money already earned by a referrer. */
    private const COMMISSION_EARNED = ['cleared'];

    /** Commission statuses still in escrow — earned but not yet confirmed. */
    private const COMMISSION_PENDING = ['pending_escrow'];

    /** Referral statuses that no longer represent a live attribution. */
    private const REFERRAL_DEAD = ['rejected', 'reversed'];

    public function index(Request $request): JsonResponse
    {
        $courses = Course::with('language')
            ->when(! $request->user()->hasRole('super_admin'), fn ($q) => $q->where('owner_user_id', $request->user()->id))
            ->withCount('levels')
            ->orderBy('title')
            ->get();

        $economics = $this->economics();

        $data = $courses->map(fn (Course $course) => $this->performanceFor($course, $economics));

        return response()->json(['data' => $data->values()]);
    }

    /**
     * Platform-wide maps built once (not per course) so this stays O(courses)
     * queries rather than O(courses × subscribers).
     *
     * @return array{
     *     payersByCourse: array<int, list<int>>,
     *     courseCountByPayer: array<int, int>,
     *     subscriptionsByPayer: array<int, list<Subscription>>,
     *     referredPayers: array<int, true>,
     *     commissionsByPayer: array<int, list<Commission>>,
     * }
     */
    private function economics(): array
    {
        // The paying user behind each enrolled learner: an adult learner pays
        // for themselves, a child's subscription sits with the family owner.
        $rows = DB::table('enrollments')
            ->join('learner_profiles', 'learner_profiles.id', '=', 'enrollments.learner_profile_id')
            ->leftJoin('families', 'families.id', '=', 'learner_profiles.family_id')
            ->select('enrollments.course_id', 'learner_profiles.user_id', 'families.owner_user_id')
            ->get();

        /** @var array<int, array<int, true>> $payersByCourse */
        $payersByCourse = [];
        /** @var array<int, array<int, true>> $coursesByPayer */
        $coursesByPayer = [];

        foreach ($rows as $row) {
            $payer = (int) ($row->user_id ?? $row->owner_user_id ?? 0);
            if ($payer === 0) {
                continue; // school-seat learner with no paying user of their own
            }
            $courseId = (int) $row->course_id;
            $payersByCourse[$courseId][$payer] = true;
            $coursesByPayer[$payer][$courseId] = true;
        }

        $payerIds = array_keys($coursesByPayer);

        /** @var array<int, list<Subscription>> $subscriptionsByPayer */
        $subscriptionsByPayer = Subscription::with('plan')
            ->where('subscriber_type', User::class)
            ->whereIn('subscriber_id', $payerIds ?: [0])
            ->get()
            ->groupBy('subscriber_id')
            ->map(fn ($group) => $group->all())
            ->all();

        $referrals = Referral::whereIn('referred_user_id', $payerIds ?: [0])
            ->whereNotIn('status', self::REFERRAL_DEAD)
            ->get();

        /** @var array<int, true> $referredPayers */
        $referredPayers = [];
        /** @var array<int, int> $payerByReferral */
        $payerByReferral = [];
        foreach ($referrals as $referral) {
            $payer = (int) $referral->referred_user_id;
            $referredPayers[$payer] = true;
            $payerByReferral[(int) $referral->id] = $payer;
        }

        /** @var array<int, list<Commission>> $commissionsByPayer */
        $commissionsByPayer = [];
        foreach (Commission::whereIn('referral_id', array_keys($payerByReferral) ?: [0])->get() as $commission) {
            $payer = $payerByReferral[(int) $commission->referral_id] ?? null;
            if ($payer !== null) {
                $commissionsByPayer[$payer][] = $commission;
            }
        }

        return [
            'payersByCourse' => array_map(fn (array $p) => array_keys($p), $payersByCourse),
            'courseCountByPayer' => array_map(fn (array $c) => count($c), $coursesByPayer),
            'subscriptionsByPayer' => $subscriptionsByPayer,
            'referredPayers' => $referredPayers,
            'commissionsByPayer' => $commissionsByPayer,
        ];
    }

    /**
     * @param  array{
     *     payersByCourse: array<int, list<int>>,
     *     courseCountByPayer: array<int, int>,
     *     subscriptionsByPayer: array<int, list<Subscription>>,
     *     referredPayers: array<int, true>,
     *     commissionsByPayer: array<int, list<Commission>>,
     * }  $economics
     * @return array<string, mixed>
     */
    private function performanceFor(Course $course, array $economics): array
    {
        $lessonIds = Lesson::whereHas('courseLevel', fn ($q) => $q->where('course_id', $course->id))->pluck('id');
        $componentIds = LessonComponent::whereIn('lesson_id', $lessonIds)->pluck('id');
        $questionIds = Question::whereIn('quiz_id', function ($q) use ($componentIds) {
            $q->select('id')->from('quizzes')->whereIn('lesson_component_id', $componentIds);
        })->pluck('id');

        $enrollmentsByStatus = Enrollment::where('course_id', $course->id)
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $enrollments = (int) $enrollmentsByStatus->sum();

        $lessonCompletions = LessonProgress::whereIn('lesson_id', $lessonIds)->where('status', 'completed')->count();

        $responses = QuestionResponse::whereIn('question_id', $questionIds)
            ->selectRaw('count(*) as answered, sum(is_correct) as correct')
            ->first();
        $answered = (int) ($responses->answered ?? 0);
        $correct = (int) ($responses->correct ?? 0);

        return [
            'id' => $course->id,
            'title' => $course->title,
            'language' => $course->language->name,
            'is_published' => $course->is_published,
            'levels_count' => $course->levels_count,
            'lessons_count' => $lessonIds->count(),
            'enrollments' => $enrollments,
            'enrollments_by_status' => $enrollmentsByStatus,
            'lesson_completions' => $lessonCompletions,
            'quiz_accuracy' => $answered > 0 ? round($correct / $answered, 2) : null,
            ...$this->earningsFor($course, $economics),
        ];
    }

    /**
     * Attributed subscription + referral economics for one course.
     *
     * @param  array{
     *     payersByCourse: array<int, list<int>>,
     *     courseCountByPayer: array<int, int>,
     *     subscriptionsByPayer: array<int, list<Subscription>>,
     *     referredPayers: array<int, true>,
     *     commissionsByPayer: array<int, list<Commission>>,
     * }  $economics
     * @return array<string, mixed>
     */
    private function earningsFor(Course $course, array $economics): array
    {
        $payers = $economics['payersByCourse'][$course->id] ?? [];

        /** @var array<string, int> $byStatus */
        $byStatus = [];
        $activeRevenue = 0;
        $pendingRevenue = 0;
        $referralRevenue = 0;
        $referredSubscribers = 0;
        $earnedCommission = 0;
        $pendingCommission = 0;

        foreach ($payers as $payer) {
            // Even split across every course this subscriber's learners touch,
            // so one subscription is never counted whole in several courses.
            $courseCount = max(1, $economics['courseCountByPayer'][$payer] ?? 1);
            $isReferred = isset($economics['referredPayers'][$payer]);

            if ($isReferred) {
                $referredSubscribers++;
            }

            foreach ($economics['subscriptionsByPayer'][$payer] ?? [] as $subscription) {
                $status = (string) $subscription->status;
                $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;

                $share = (int) round(((int) ($subscription->plan->price_minor ?? 0)) / $courseCount);

                if ($status === 'active') {
                    $activeRevenue += $share;
                    if ($isReferred) {
                        $referralRevenue += $share;
                    }
                } elseif ($status === 'pending') {
                    $pendingRevenue += $share;
                }
            }

            foreach ($economics['commissionsByPayer'][$payer] ?? [] as $commission) {
                $share = (int) round(((int) $commission->amount_minor) / $courseCount);

                if (in_array($commission->status, self::COMMISSION_EARNED, true)) {
                    $earnedCommission += $share;
                } elseif (in_array($commission->status, self::COMMISSION_PENDING, true)) {
                    $pendingCommission += $share;
                }
            }
        }

        return [
            'subscribers' => count($payers),
            'subscriptions_by_status' => $byStatus,
            'referred_subscribers' => $referredSubscribers,
            'attributed_revenue_minor' => $activeRevenue,
            'pending_revenue_minor' => $pendingRevenue,
            'referral_revenue_minor' => $referralRevenue,
            'referral_commission_minor' => $earnedCommission,
            'pending_commission_minor' => $pendingCommission,
        ];
    }
}
