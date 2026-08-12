<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonComponent;
use App\Models\LessonProgress;
use App\Models\Question;
use App\Models\QuestionResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "How is my content performing" for the content_owner role. MAHADUM.360 sells
 * platform subscriptions, not individual courses — there is no per-course
 * purchase/revenue record to report, and platform-wide revenue is intentionally
 * super_admin-only (`analytics.platform.view`, see Roles_Permissions.md §4).
 * What genuinely exists per course: enrollments, lesson completions, and quiz
 * accuracy — this rolls those up per course, scoped to the courses the caller
 * owns (super_admin sees every course, via Gate::before).
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

        $data = $courses->map(fn (Course $course) => $this->performanceFor($course));

        return response()->json(['data' => $data->values()]);
    }

    /** @return array<string, mixed> */
    private function performanceFor(Course $course): array
    {
        $lessonIds = Lesson::whereHas('courseLevel', fn ($q) => $q->where('course_id', $course->id))->pluck('id');
        $componentIds = LessonComponent::whereIn('lesson_id', $lessonIds)->pluck('id');
        $questionIds = Question::whereIn('quiz_id', function ($q) use ($componentIds) {
            $q->select('id')->from('quizzes')->whereIn('lesson_component_id', $componentIds);
        })->pluck('id');

        $enrollments = Enrollment::where('course_id', $course->id)->count();
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
            'lesson_completions' => $lessonCompletions,
            'quiz_accuracy' => $answered > 0 ? round($correct / $answered, 2) : null,
        ];
    }
}
