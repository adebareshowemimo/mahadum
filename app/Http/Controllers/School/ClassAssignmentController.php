<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Concerns\ResolvesLearner;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\GradeClassAssignmentSubmissionRequest;
use App\Http\Requests\School\StoreClassAssignmentRequest;
use App\Http\Requests\School\StoreClassAssignmentSubmissionRequest;
use App\Models\ClassAssignment;
use App\Models\ClassAssignmentSubmission;
use App\Models\MediaAsset;
use App\Models\SchoolClass;
use App\Notifications\ClassAssignmentGraded;
use App\Services\AuditLogger;
use App\Services\Family\FamilyAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Teacher-authored class assignments (schools.assignments.{create,review}),
 * distinct from the CMS `Assignment` (lesson-component) and the family
 * `AssignmentSubmission`/parent-review flow. Only the class's own teacher may
 * grade. School admins can also create work in their own school; creation is
 * enforced by the class policy as well as the existing permission guard.
 */
class ClassAssignmentController extends Controller
{
    use ResolvesLearner;

    public function __construct(
        private AuditLogger $audit,
    ) {}

    /** List a class's assignments with submission progress. */
    public function index(SchoolClass $class): JsonResponse
    {
        $total = $class->enrollments()->count();

        $assignments = $class->assignments()
            ->withCount([
                'submissions',
                'submissions as graded_count' => fn ($q) => $q->where('status', 'graded'),
            ])
            ->latest()
            ->get()
            ->map(fn (ClassAssignment $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'due_at' => $a->due_at,
                'coin_reward' => $a->coin_reward,
                'total_students' => $total,
                'submitted_count' => $a->submissions_count,
                'graded_count' => (int) $a->getAttribute('graded_count'),
            ]);

        return response()->json(['data' => $assignments->values()]);
    }

    /** Per-student completion across every assignment in the class (submitted / total). */
    public function completion(SchoolClass $class): JsonResponse
    {
        $totalAssignments = $class->assignments()->count();

        $submittedCounts = ClassAssignmentSubmission::whereHas(
            'classAssignment',
            fn ($q) => $q->where('school_class_id', $class->id),
        )
            ->whereIn('status', ['submitted', 'graded'])
            ->selectRaw('learner_profile_id, COUNT(*) as c')
            ->groupBy('learner_profile_id')
            ->pluck('c', 'learner_profile_id');

        $roster = $class->enrollments()->with('learnerProfile')->get()->map(function ($enrollment) use ($submittedCounts, $totalAssignments) {
            $submitted = (int) ($submittedCounts[$enrollment->learner_profile_id] ?? 0);

            return [
                'learner_id' => $enrollment->learner_profile_id,
                'display_name' => $enrollment->learnerProfile?->display_name,
                'submitted' => $submitted,
                'total' => $totalAssignments,
                'rate' => $totalAssignments > 0 ? (int) round($submitted / $totalAssignments * 100) : null,
            ];
        });

        return response()->json(['data' => $roster->values()]);
    }

    public function store(StoreClassAssignmentRequest $request, SchoolClass $class): JsonResponse
    {
        Gate::authorize('createAssignment', $class);

        $assignment = ClassAssignment::create([
            ...$request->validated(),
            'school_class_id' => $class->id,
            'created_by' => $request->user()->id,
        ]);

        $this->audit->record('class_assignment.created', $assignment, [], $assignment->only(['title', 'school_class_id', 'coin_reward', 'due_at']), $class->organization_id);

        return response()->json(['data' => ['id' => $assignment->id, 'title' => $assignment->title]], 201);
    }

    /** Assignment detail + a roster of every enrolled student and their submission status. */
    public function show(Request $request, SchoolClass $class, ClassAssignment $assignment): JsonResponse
    {
        abort_unless($assignment->school_class_id === $class->id, 404);

        $submissions = $assignment->submissions()->with('learnerProfile', 'mediaAsset')->get()->keyBy('learner_profile_id');

        $roster = $class->enrollments()->with('learnerProfile')->get()->map(function ($enrollment) use ($submissions) {
            $s = $submissions->get($enrollment->learner_profile_id);

            return [
                'learner_id' => $enrollment->learner_profile_id,
                'display_name' => $enrollment->learnerProfile?->display_name,
                'submission_id' => $s?->id,
                'status' => $s?->status,
                'passed' => $s?->passed,
                'score' => $s?->score,
                'feedback' => $s?->feedback,
                'submitted_at' => $s?->submitted_at,
                'graded_at' => $s?->graded_at,
                'media_url' => $s?->mediaAsset ? Storage::disk('public')->url($s->mediaAsset->url) : null,
                'media_type' => $s?->mediaAsset?->type,
                'text_body' => $s?->text_body,
                'parent_review_status' => $s?->parent_review_status,
            ];
        });

        return response()->json(['data' => [
            'id' => $assignment->id,
            'title' => $assignment->title,
            'instructions' => $assignment->instructions,
            'due_at' => $assignment->due_at,
            'coin_reward' => $assignment->coin_reward,
            'can_grade' => $class->teacher_user_id === $request->user()->id && Gate::allows('gradeAssignment', $class),
            'roster' => $roster->values(),
        ]]);
    }

    /**
     * A learner (or their parent) submits work for a class assignment. The
     * learner must be self/parent-owned and enrolled in the assignment's class.
     * Same-tenant staff read access does not grant submission authority.
     */
    public function submit(StoreClassAssignmentSubmissionRequest $request, ClassAssignment $assignment): JsonResponse
    {
        $learner = $this->learner($request->integer('learner_id'));
        Gate::authorize('redeemReward', $learner);
        $enrolled = $assignment->schoolClass->enrollments()->where('learner_profile_id', $learner->id)->exists();
        abort_unless($enrolled, 403, 'This learner is not enrolled in that class.');

        $submission = DB::transaction(function () use ($request, $assignment, $learner) {
            // Serialize submissions for this assignment; graded work cannot be
            // reset and rewarded again by resubmitting it.
            ClassAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $existing = $assignment->submissions()->where('learner_profile_id', $learner->id)->lockForUpdate()->first();
            abort_if($existing?->status === 'graded', 422, 'This assignment has already been graded.');
            $assetId = null;
            if ($request->hasFile('media')) {
                $path = $request->file('media')->store('class-assignments', 'public');
                $assetId = MediaAsset::create([
                    'type' => str_starts_with((string) $request->file('media')->getMimeType(), 'video/') ? 'video' : 'audio',
                    'url' => $path,
                    'uploaded_by' => $request->user()->id,
                ])->id;
            }

            return ClassAssignmentSubmission::updateOrCreate(
                ['class_assignment_id' => $assignment->id, 'learner_profile_id' => $learner->id],
                ['media_asset_id' => $assetId, 'text_body' => $request->input('text_body'), 'status' => 'submitted', 'submitted_at' => now()],
            );
        });

        return response()->json(['data' => [
            'id' => $submission->id,
            'status' => $submission->status,
        ]], 201);
    }

    /**
     * Grade the work. A pass locks its reward for parent approval; grading
     * alone cannot release assignment coins (Rule 8).
     */
    public function grade(
        GradeClassAssignmentSubmissionRequest $request,
        SchoolClass $class,
        ClassAssignment $assignment,
        ClassAssignmentSubmission $submission,
    ): JsonResponse {
        Gate::authorize('gradeAssignment', $class);
        abort_unless($class->teacher_user_id === $request->user()->id, 403, 'Only this class\'s teacher can grade submissions.');
        abort_unless($assignment->school_class_id === $class->id, 404);
        abort_unless($submission->class_assignment_id === $assignment->id, 404);
        abort_unless($submission->status !== 'graded', 422, 'This submission has already been graded.');

        $passed = $request->boolean('passed');
        $learner = $submission->learnerProfile;

        $coinsReleased = DB::transaction(function () use ($request, $submission, $assignment, $class, $passed) {
            $submission = ClassAssignmentSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            abort_unless($submission->status === 'submitted', 422, 'This submission has already been graded.');
            $locked = $passed ? $assignment->coin_reward : 0;

            $submission->update([
                'status' => 'graded',
                'passed' => $passed,
                'score' => $request->input('score'),
                'feedback' => $request->input('feedback'),
                'graded_by' => $request->user()->id,
                'graded_at' => now(),
                'coins_locked' => $locked,
                'parent_review_status' => $locked > 0 ? 'pending' : 'not_required',
            ]);

            $this->audit->record(
                'class_assignment.graded',
                $submission,
                ['status' => 'submitted'],
                ['status' => 'graded', 'passed' => $passed, 'coins_released' => 0, 'coins_locked' => $locked],
                $class->organization_id,
            );

            return 0;
        });

        // Sent after commit so a queued mail job never races a rolled-back transaction.
        $learner?->user?->notify(new ClassAssignmentGraded($submission->refresh(), $coinsReleased));
        if ($learner) {
            app(FamilyAlertService::class)->forLearner($learner);
        }

        return response()->json(['data' => [
            'submission_id' => $submission->id,
            'status' => $submission->fresh()->status,
            'passed' => $passed,
            'coins_released' => $coinsReleased,
        ]]);
    }
}
