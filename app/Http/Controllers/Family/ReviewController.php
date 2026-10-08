<?php

namespace App\Http\Controllers\Family;

use App\Http\Controllers\Concerns\ResolvesFamily;
use App\Http\Controllers\Controller;
use App\Http\Requests\Family\ReviewAssignmentRequest;
use App\Models\AssignmentSubmission;
use App\Models\ClassAssignmentSubmission;
use App\Models\LearnerProfile;
use App\Models\SpeakingSubmission;
use App\Notifications\AssignmentApproved;
use App\Services\AuditLogger;
use App\Services\Family\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReviewController extends Controller
{
    use ResolvesFamily;

    public function __construct(
        private WalletService $wallets,
        private AuditLogger $audit,
    ) {}

    /** The parent's review queue: chores + speaking + assignment submissions. */
    public function pending(Request $request): JsonResponse
    {
        $family = $this->family($request->user());
        $learnerIds = $family->learnerProfiles()->pluck('id');

        $chores = $family->chores()
            ->where('status', 'pending_review')
            ->whereHas('submissions', fn ($q) => $q->whereNotNull('submitted_at')->whereNull('decision'))
            ->with('assigneeLearnerProfile', 'submissions')
            ->get()
            ->map(fn ($c) => [
                'chore_id' => $c->id,
                'title' => $c->title,
                'assignee' => $c->assigneeLearnerProfile?->display_name,
                'coin_reward' => $c->coin_reward,
                'status' => $c->status,
                'evidence_type' => $c->submissions->first()?->evidence_type,
                'submitted_at' => $c->submissions->first()?->submitted_at,
            ]);

        $speaking = SpeakingSubmission::whereIn('learner_profile_id', $learnerIds)
            ->where('status', 'needs_review')
            ->get(['id', 'learner_profile_id', 'lesson_component_id', 'status']);

        $assignments = AssignmentSubmission::whereIn('learner_profile_id', $learnerIds)
            ->where('parent_review_status', 'pending')
            ->with(['learnerProfile', 'mediaAsset', 'lessonComponent.assignment'])
            ->latest()
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'learner_profile_id' => $s->learner_profile_id,
                'lesson_component_id' => $s->lesson_component_id,
                'parent_review_status' => $s->parent_review_status,
                'learner' => $s->learnerProfile?->display_name,
                'prompt' => $s->lessonComponent->assignment?->prompt,
                'expected_media' => $s->lessonComponent->assignment?->expected_media,
                'coin_reward' => $s->coins_locked,
                'media_url' => $s->mediaAsset ? Storage::disk('public')->url($s->mediaAsset->url) : null,
            ]);

        return response()->json(['data' => [
            'chores' => $chores->values(),
            'speaking' => $speaking,
            'assignments' => $assignments->values(),
            'class_assignments' => ClassAssignmentSubmission::whereIn('learner_profile_id', $learnerIds)
                ->where('parent_review_status', 'pending')->where('status', 'graded')->where('passed', true)
                ->with('learnerProfile', 'classAssignment', 'mediaAsset')->get()->map(fn ($s) => [
                    'id' => $s->id, 'learner' => $s->learnerProfile?->display_name,
                    'title' => $s->classAssignment->title, 'coin_reward' => $s->coins_locked,
                    'text_body' => $s->text_body, 'feedback' => $s->feedback,
                    'media_url' => $s->mediaAsset ? Storage::disk('public')->url($s->mediaAsset->url) : null,
                    'media_type' => $s->mediaAsset?->type,
                ]),
        ]]);
    }

    public function reviewClassAssignment(ReviewAssignmentRequest $request, ClassAssignmentSubmission $submission): JsonResponse
    {
        $family = $this->family($request->user());
        $learner = $submission->learnerProfile;
        abort_unless($learner && $learner->family_id === $family->id && $learner->user_id !== $request->user()->id, 403);
        $decision = $request->string('decision')->value();

        $released = DB::transaction(function () use ($request, $submission, $family, $decision) {
            $submission = ClassAssignmentSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $learner = LearnerProfile::whereKey($submission->learner_profile_id)->lockForUpdate()->first();
            abort_unless($learner && $learner->family_id === $family->id && $learner->user_id !== $request->user()->id, 403, 'You may only review another learner in your own family.');
            abort_unless($submission->status === 'graded' && $submission->passed && $submission->parent_review_status === 'pending', 422, 'This assignment is not waiting for parent approval.');
            $released = $decision === 'approve' ? $submission->coins_locked : 0;
            if ($released > 0) {
                $this->wallets->rewardFromParent($learner, $released, 'class_assignment', $submission);
            }
            $submission->update(['parent_review_status' => $decision === 'approve' ? 'approved' : 'rejected', 'decided_by' => $request->user()->id, 'decided_at' => now()]);
            $this->audit->record('class_assignment.parent_reviewed', $submission, ['parent_review_status' => 'pending'], ['parent_review_status' => $submission->parent_review_status, 'coins_released' => $released]);

            return $released;
        });

        return response()->json(['data' => ['submission_id' => $submission->id, 'status' => $submission->fresh()->parent_review_status, 'coins_released' => $released]]);
    }

    /**
     * Parent review of an assignment clip. Escrowed coins (`coins_locked`) are
     * released to the learner's wallet ONLY on approval — atomically with the
     * decision, and audited. Separation of duties: the reviewer is a parent in
     * the learner's family, never the beneficiary.
     */
    public function review(ReviewAssignmentRequest $request, AssignmentSubmission $submission): JsonResponse
    {
        $family = $this->family($request->user());
        $learner = $submission->learnerProfile;
        abort_unless($learner && $learner->family_id === $family->id, 403, 'Not your family assignment.');
        abort_if($learner->user_id === $request->user()->id, 403, 'You cannot review your own learner assignment.');
        abort_unless($submission->parent_review_status === 'pending', 422, 'This assignment has already been reviewed.');

        $decision = $request->string('decision')->value();

        $coinsReleased = DB::transaction(function () use ($request, $submission, $family, $decision) {
            $submission = AssignmentSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $learner = LearnerProfile::whereKey($submission->learner_profile_id)->lockForUpdate()->first();
            abort_unless($learner && $learner->family_id === $family->id && $learner->user_id !== $request->user()->id, 403, 'You may only review another learner in your own family.');
            abort_unless($submission->parent_review_status === 'pending', 422, 'This assignment has already been reviewed.');
            $released = 0;
            $status = $decision === 'approve' ? 'approved' : 'rejected';

            if ($decision === 'approve' && $submission->coins_locked > 0) {
                $this->wallets->rewardFromParent(
                    $learner,
                    $submission->coins_locked,
                    'assignment',
                    $submission,
                );
                $released = $submission->coins_locked;
            }

            $submission->update([
                'parent_review_status' => $status,
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
            ]);

            $this->audit->record(
                'assignment.reviewed',
                $submission,
                ['parent_review_status' => 'pending'],
                ['parent_review_status' => $status, 'coins_released' => $released],
            );

            return $released;
        });

        // Sent after commit so a queued mail job never races a rolled-back transaction.
        if ($decision === 'approve') {
            rescue(fn () => $learner->user?->notify(new AssignmentApproved($submission, $coinsReleased)), report: true);
        }

        return response()->json(['data' => [
            'submission_id' => $submission->id,
            'status' => $submission->fresh()->parent_review_status,
            'coins_released' => $coinsReleased,
        ]]);
    }
}
