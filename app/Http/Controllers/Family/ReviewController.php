<?php

namespace App\Http\Controllers\Family;

use App\Http\Controllers\Concerns\ResolvesFamily;
use App\Http\Controllers\Controller;
use App\Http\Requests\Family\ReviewAssignmentRequest;
use App\Models\AssignmentSubmission;
use App\Models\SpeakingSubmission;
use App\Services\AuditLogger;
use App\Services\Family\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            ->whereIn('status', ['active', 'pending_review'])
            ->with('assigneeLearnerProfile')
            ->get()
            ->map(fn ($c) => [
                'chore_id' => $c->id,
                'title' => $c->title,
                'assignee' => $c->assigneeLearnerProfile?->display_name,
                'coin_reward' => $c->coin_reward,
                'status' => $c->status,
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
                'media_url' => $s->mediaAsset ? url('storage/'.$s->mediaAsset->url) : null,
            ]);

        return response()->json(['data' => [
            'chores' => $chores->values(),
            'speaking' => $speaking,
            'assignments' => $assignments->values(),
        ]]);
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
        abort_unless($submission->parent_review_status === 'pending', 422, 'This assignment has already been reviewed.');

        $decision = $request->string('decision')->value();

        $coinsReleased = DB::transaction(function () use ($request, $submission, $learner, $decision) {
            $released = 0;
            $status = $decision === 'approve' ? 'approved' : 'rejected';

            if ($decision === 'approve' && $submission->coins_locked > 0) {
                $this->wallets->credit(
                    $this->wallets->walletFor($learner),
                    $submission->coins_locked,
                    'assignment',
                    $learner->id,
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

        return response()->json(['data' => [
            'submission_id' => $submission->id,
            'status' => $submission->fresh()->parent_review_status,
            'coins_released' => $coinsReleased,
        ]]);
    }
}
