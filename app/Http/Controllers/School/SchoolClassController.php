<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreClassLearnerRequest;
use App\Http\Requests\School\StoreSchoolClassRequest;
use App\Models\ClassAssignmentSubmission;
use App\Models\ClassEnrollment;
use App\Models\LearnerProfile;
use App\Models\LessonProgress;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\QuestionResponse;
use App\Models\SchoolClass;
use App\Models\SpeakingSubmission;
use App\Services\School\ClassCourseEnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Org-scoped via the BelongsToTenant global scope (auto-filters + auto-fills
 * organization_id from the active tenant). Record access is gated by
 * SchoolClassPolicy (permission + same-tenant; teachers see their own class).
 */
class SchoolClassController extends Controller
{
    use ResolvesOrganization;

    public function __construct(private ClassCourseEnrollmentService $courseEnrollments) {}

    /** Teachers a school admin can assign to a class (dropdown source). */
    public function teachers(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeOrg($request->user(), $organization);

        $teachers = OrganizationUser::with('user')
            ->where('organization_id', $organization->id)
            ->where('role', 'teacher')
            ->where('status', 'active')
            ->get()
            ->map(fn ($m) => ['id' => $m->user?->id, 'name' => $m->user?->name])
            ->filter(fn ($t) => $t['id'] !== null)
            ->values();

        return response()->json(['data' => $teachers]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = SchoolClass::with('teacherUser')->withCount('enrollments');

        // ?mine=1 — only classes this user teaches (used by the Teacher Profile page).
        if ($request->boolean('mine')) {
            $query->where('teacher_user_id', $request->user()->id);
        }

        $classes = $query->get()
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
        $values = $request->validated();

        // A teacher creates a classroom for themselves. They cannot use the
        // request payload to assign it to another teacher.
        if ($request->user()->hasRole('teacher') && ! $request->user()->hasRole('school_admin')) {
            $values['teacher_user_id'] = $request->user()->id;
        }

        $class = SchoolClass::create($values); // organization_id auto-filled

        return response()->json(['data' => ['id' => $class->id, 'name' => $class->name]], 201);
    }

    public function update(StoreSchoolClassRequest $request, SchoolClass $class): JsonResponse
    {
        $values = $request->validated();
        if ($request->user()->hasRole('teacher') && ! $request->user()->hasRole('school_admin')) {
            unset($values['teacher_user_id']);
        }

        $class->update($values);

        return response()->json(['data' => ['id' => $class->id, 'name' => $class->name]]);
    }

    /** School learners who are not already members of this class. */
    public function availableLearners(Request $request, SchoolClass $class): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        $learners = LearnerProfile::query()
            ->with('user:id,email')
            ->where('organization_id', $class->organization_id)
            ->whereDoesntHave('classEnrollments', fn ($query) => $query->where('school_class_id', $class->id))
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
                $query->where(function ($query) use ($like) {
                    $query->where('display_name', 'like', $like)
                        ->orWhereHas('user', fn ($query) => $query->where('email', 'like', $like));
                });
            })
            ->orderBy('display_name')
            ->limit(20)
            ->get(['id', 'user_id', 'display_name', 'age_band'])
            ->map(fn (LearnerProfile $learner) => [
                'id' => $learner->id,
                'display_name' => $learner->display_name,
                'level' => $learner->age_band,
                'email' => $learner->user?->email,
            ]);

        return response()->json(['data' => $learners]);
    }

    /** Add an existing learner from this school. New learners use invitations. */
    public function addLearner(StoreClassLearnerRequest $request, SchoolClass $class): JsonResponse
    {
        [$learner, $coursesEnrolled] = DB::transaction(function () use ($request, $class) {
            $learner = LearnerProfile::where('organization_id', $class->organization_id)
                ->findOrFail($request->integer('learner_id'));

            ClassEnrollment::firstOrCreate([
                'school_class_id' => $class->id,
                'learner_profile_id' => $learner->id,
            ]);

            return [$learner, $this->courseEnrollments->syncLearner($class, $learner)];
        });

        return response()->json(['data' => [
            'learner_id' => $learner->id,
            'display_name' => $learner->display_name,
            'courses_enrolled' => $coursesEnrolled,
        ]], 201);
    }
}
