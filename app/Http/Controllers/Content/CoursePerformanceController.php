<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Family;
use App\Models\Lesson;
use App\Models\LessonComponent;
use App\Models\LessonProgress;
use App\Models\Question;
use App\Models\QuestionResponse;
use App\Models\Referral;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * "How is my content performing" for the content_owner role. MAHADUM.360 sells
 * platform subscriptions, not individual courses — there is no per-course
 * purchase/revenue record to report, and platform-wide revenue is intentionally
 * super_admin-only (`analytics.platform.view`, see Roles_Permissions.md §4).
 * What genuinely exists per course: enrollments (with their status breakdown),
 * lesson completions, quiz accuracy, and — as an approximation, not a real
 * revenue figure — how many of a course's enrolled learners belong to a
 * referred family. This rolls those up per course, scoped to the courses the
 * caller owns (super_admin sees every course, via Gate::before).
 */
class CoursePerformanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $courses = Course::with('language')
            ->when(! $request->user()->hasRole('super_admin'), fn ($q) => $q->where('owner_user_id', $request->user()->id))
            ->withCount('levels')
            ->orderBy('title')
            ->get();

        // Families whose signup is attributed to a referral — computed once
        // rather than per course. Enrollments belonging to these families'
        // learners are the "referral users" approximation below.
        $referredUserIds = Referral::whereNotNull('referred_user_id')->pluck('referred_user_id');
        $referredFamilyIds = Family::whereIn('owner_user_id', $referredUserIds)->pluck('id');

        $data = $courses->map(fn (Course $course) => $this->performanceFor($course, $referredFamilyIds));

        return response()->json(['data' => $data->values()]);
    }

    /**
     * @param  Collection<int, int>  $referredFamilyIds
     * @return array<string, mixed>
     */
    private function performanceFor(Course $course, Collection $referredFamilyIds): array
    {
        $lessonIds = Lesson::whereHas('courseLevel', fn ($q) => $q->where('course_id', $course->id))->pluck('id');
        $componentIds = LessonComponent::whereIn('lesson_id', $lessonIds)->pluck('id');
        $questionIds = Question::whereIn('quiz_id', function ($q) use ($componentIds) {
            $q->select('id')->from('quizzes')->whereIn('lesson_component_id', $componentIds);
        })->pluck('id');

        $enrollmentsByStatus = Enrollment::where('course_id', $course->id)
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $enrollments = (int) $enrollmentsByStatus->sum();

        $referredEnrollments = Enrollment::where('course_id', $course->id)
            ->whereHas('learnerProfile', fn ($q) => $q->whereIn('family_id', $referredFamilyIds))
            ->count();

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
            'referred_enrollments' => $referredEnrollments,
            'lesson_completions' => $lessonCompletions,
            'quiz_accuracy' => $answered > 0 ? round($correct / $answered, 2) : null,
        ];
    }
}
