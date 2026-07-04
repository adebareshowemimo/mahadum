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
use App\Services\Family\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Teacher-authored class assignments (schools.assignments.{create,review}),
 * distinct from the CMS `Assignment` (lesson-component) and the family
 * `AssignmentSubmission`/parent-review flow. Only the class's own teacher may
 * create or grade — enforced here (not via a class-wide policy) because these
 * two permissions are granted to `teacher` alone.
 */
class ClassAssignmentController extends Controller
{
    use ResolvesLearner;

    public function __construct(
        private WalletService $wallets,
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

    public function store(StoreClassAssignmentRequest $request, SchoolClass $class): JsonResponse
    {
        abort_unless($class->teacher_user_id === $request->user()->id, 403, 'Only this class\'s teacher can create assignments.');

        $assignment = ClassAssignment::create([
            ...$request->validated(),
            'school_class_id' => $class->id,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => ['id' => $assignment->id, 'title' => $assignment->title]], 201);
    }

    /** Assignment detail + a roster of every enrolled student and their submission status. */
    public function show(SchoolClass $class, ClassAssignment $assignment): JsonResponse
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
                'media_url' => $s?->mediaAsset ? url('storage/'.$s->mediaAsset->url) : null,
            ];
        });

        return response()->json(['data' => [
            'id' => $assignment->id,
            'title' => $assignment->title,
            'instructions' => $assignment->instructions,
            'due_at' => $assignment->due_at,
            'coin_reward' => $assignment->coin_reward,
            'roster' => $roster->values(),
        ]]);
    }

    /**
     * A learner (or their parent) submits work for a class assignment. The
     * learner must be self/parent-owned or same-tenant staff per
     * LearnerProfilePolicy::view (ResolvesLearner::learner()), and must be
     * enrolled in the assignment's class.
     */
    public function submit(StoreClassAssignmentSubmissionRequest $request, ClassAssignment $assignment): JsonResponse
    {
        $learner = $this->learner($request->integer('learner_id'));
        $enrolled = $assignment->schoolClass->enrollments()->where('learner_profile_id', $learner->id)->exists();
        abort_unless($enrolled, 403, 'This learner is not enrolled in that class.');

        $submission = DB::transaction(function () use ($request, $assignment, $learner) {
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
                ['media_asset_id' => $assetId, 'status' => 'submitted', 'submitted_at' => now()],
            );
        });

        return response()->json(['data' => [
            'id' => $submission->id,
            'status' => $submission->status,
        ]], 201);
    }

    /**
     * Grade a submission. Coins release ONLY on pass — atomically with the
     * decision, and audited. Mirrors the parent-approval separation-of-duties
     * pattern (ReviewController@review), but the approver here is the class's
     * own teacher rather than a parent.
     */
    public function grade(
        GradeClassAssignmentSubmissionRequest $request,
        SchoolClass $class,
        ClassAssignment $assignment,
        ClassAssignmentSubmission $submission,
    ): JsonResponse {
        abort_unless($class->teacher_user_id === $request->user()->id, 403, 'Only this class\'s teacher can grade submissions.');
        abort_unless($assignment->school_class_id === $class->id, 404);
        abort_unless($submission->class_assignment_id === $assignment->id, 404);
        abort_unless($submission->status !== 'graded', 422, 'This submission has already been graded.');

        $passed = $request->boolean('passed');
        $learner = $submission->learnerProfile;

        $coinsReleased = DB::transaction(function () use ($request, $submission, $assignment, $class, $learner, $passed) {
            $released = 0;

            if ($passed && $assignment->coin_reward > 0 && $learner) {
                $this->wallets->credit(
                    $this->wallets->walletFor($learner),
                    $assignment->coin_reward,
                    'class_assignment',
                    $learner->id,
                    $submission,
                );
                $released = $assignment->coin_reward;
            }

            $submission->update([
                'status' => 'graded',
                'passed' => $passed,
                'score' => $request->input('score'),
                'feedback' => $request->input('feedback'),
                'graded_by' => $request->user()->id,
                'graded_at' => now(),
            ]);

            $this->audit->record(
                'class_assignment.graded',
                $submission,
                ['status' => 'submitted'],
                ['status' => 'graded', 'passed' => $passed, 'coins_released' => $released],
                $class->organization_id,
            );

            return $released;
        });

        // Sent after commit so a queued mail job never races a rolled-back transaction.
        $learner?->user?->notify(new ClassAssignmentGraded($submission, $coinsReleased));

        return response()->json(['data' => [
            'submission_id' => $submission->id,
            'status' => $submission->fresh()->status,
            'passed' => $passed,
            'coins_released' => $coinsReleased,
        ]]);
    }
}
