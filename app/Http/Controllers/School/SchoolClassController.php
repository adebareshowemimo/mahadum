<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreSchoolClassRequest;
use App\Models\ClassAssignmentSubmission;
use App\Models\LessonProgress;
use App\Models\QuestionResponse;
use App\Models\SchoolClass;
use App\Models\SpeakingSubmission;
use Illuminate\Http\JsonResponse;

/**
 * Org-scoped via the BelongsToTenant global scope (auto-filters + auto-fills
 * organization_id from the active tenant). Record access is gated by
 * SchoolClassPolicy (permission + same-tenant; teachers see their own class).
 */
class SchoolClassController extends Controller
{
    public function index(): JsonResponse
    {
        $classes = SchoolClass::with('teacherUser')->withCount('enrollments')->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'level' => $c->level,
                'teacher' => $c->teacherUser?->name,
                'students' => $c->enrollments_count,
            ]);

        return response()->json(['data' => $classes]);
    }

    public function show(SchoolClass $class): JsonResponse
    {
        $class->load('teacherUser', 'enrollments.learnerProfile');

        return response()->json(['data' => [
            'id' => $class->id,
            'name' => $class->name,
            'level' => $class->level,
            'teacher' => $class->teacherUser?->name,
            'students' => $class->enrollments->map(fn ($e) => [
                'learner_id' => $e->learner_profile_id,
                'display_name' => $e->learnerProfile?->display_name,
            ])->values(),
        ]]);
    }

    /**
     * Per-student learning analytics for a class: lessons completed, average
     * lesson score, quiz accuracy, and speaking submissions. Aggregated in one
     * grouped query each (portable across MySQL/sqlite).
     */
    public function analytics(SchoolClass $class): JsonResponse
    {
        $class->load('enrollments.learnerProfile');
        $ids = $class->enrollments->pluck('learner_profile_id');

        $progress = LessonProgress::whereIn('learner_profile_id', $ids)
            ->selectRaw("learner_profile_id, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed, AVG(score) as avg_score")
            ->groupBy('learner_profile_id')->get()->keyBy('learner_profile_id');

        $quiz = QuestionResponse::whereIn('learner_profile_id', $ids)
            ->selectRaw('learner_profile_id, COUNT(*) as total, SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct')
            ->groupBy('learner_profile_id')->get()->keyBy('learner_profile_id');

        $speaking = SpeakingSubmission::whereIn('learner_profile_id', $ids)
            ->selectRaw('learner_profile_id, COUNT(*) as c')
            ->groupBy('learner_profile_id')->get()->keyBy('learner_profile_id');

        $assignments = ClassAssignmentSubmission::whereIn('learner_profile_id', $ids)
            ->whereHas('classAssignment', fn ($q) => $q->where('school_class_id', $class->id))
            ->selectRaw('learner_profile_id, COUNT(*) as total, SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as passed')
            ->groupBy('learner_profile_id')->get()->keyBy('learner_profile_id');

        $students = $class->enrollments->map(function ($e) use ($progress, $quiz, $speaking, $assignments) {
            $id = $e->learner_profile_id;
            $p = $progress->get($id);
            $qz = $quiz->get($id);
            $a = $assignments->get($id);

            $quizTotal = $qz ? (int) $qz->getAttribute('total') : 0;
            $quizCorrect = $qz ? (int) $qz->getAttribute('correct') : 0;

            return [
                'learner_id' => $id,
                'display_name' => $e->learnerProfile?->display_name,
                'lessons_completed' => $p ? (int) $p->getAttribute('completed') : 0,
                'avg_score' => $p && $p->getAttribute('avg_score') !== null ? (int) round((float) $p->getAttribute('avg_score')) : null,
                'quiz_total' => $quizTotal,
                'quiz_correct' => $quizCorrect,
                'quiz_accuracy' => $quizTotal > 0 ? (int) round($quizCorrect / $quizTotal * 100) : null,
                'speaking_count' => ($s = $speaking->get($id)) ? (int) $s->getAttribute('c') : 0,
                'assignments_submitted' => $a ? (int) $a->getAttribute('total') : 0,
                'assignments_passed' => $a ? (int) $a->getAttribute('passed') : 0,
            ];
        })->values();

        return response()->json(['data' => [
            'class' => ['id' => $class->id, 'name' => $class->name],
            'students' => $students,
        ]]);
    }

    public function store(StoreSchoolClassRequest $request): JsonResponse
    {
        $class = SchoolClass::create($request->validated()); // organization_id auto-filled

        return response()->json(['data' => ['id' => $class->id, 'name' => $class->name]], 201);
    }

    public function update(StoreSchoolClassRequest $request, SchoolClass $class): JsonResponse
    {
        $class->update($request->validated());

        return response()->json(['data' => ['id' => $class->id, 'name' => $class->name]]);
    }
}
