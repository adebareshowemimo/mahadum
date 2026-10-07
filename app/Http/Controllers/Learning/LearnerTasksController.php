<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Models\Chore;
use App\Models\ChoreSubmission;
use App\Models\ClassAssignment;
use App\Models\LearnerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class LearnerTasksController extends Controller
{
    public function index(LearnerProfile $learner): JsonResponse
    {
        Gate::authorize('redeemReward', $learner);

        return response()->json(['data' => [
            'chores' => Chore::where('assignee_learner_profile_id', $learner->id)
                ->with('submissions')->get()->map(fn ($c) => [
                    'id' => $c->id, 'title' => $c->title, 'description' => $c->description,
                    'coin_reward' => $c->coin_reward, 'status' => $c->status, 'due_at' => $c->due_at,
                    'review_decision' => $c->submissions->first()?->decision,
                ]),
            'assignments' => ClassAssignment::whereHas('schoolClass.enrollments', fn ($q) => $q->where('learner_profile_id', $learner->id))
                ->with(['schoolClass', 'submissions' => fn ($q) => $q->where('learner_profile_id', $learner->id)])
                ->latest()->get()->map(fn ($a) => [
                    'id' => $a->id, 'title' => $a->title, 'instructions' => $a->instructions,
                    'class_name' => $a->schoolClass->name, 'due_at' => $a->due_at,
                    'coin_reward' => $a->coin_reward, 'status' => $a->submissions->first()?->status,
                    'feedback' => $a->submissions->first()?->feedback,
                    'parent_review_status' => $a->submissions->first()?->parent_review_status,
                ]),
        ]]);
    }

    /** A checkbox completion is evidence; no child recording is required. */
    public function submitChore(Request $request, Chore $chore): JsonResponse
    {
        $request->validate(['learner_id' => ['required', 'integer'], 'completed' => ['required', 'accepted']]);
        $learner = LearnerProfile::findOrFail($request->integer('learner_id'));
        Gate::authorize('redeemReward', $learner);
        abort_unless($chore->assignee_learner_profile_id === $learner->id && $chore->family_id === $learner->family_id, 403);

        DB::transaction(function () use ($chore) {
            $chore = Chore::whereKey($chore->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($chore->status, ['active', 'rejected', 'pending_review'], true), 422, 'This chore has already been approved.');
            $submission = ChoreSubmission::where('chore_id', $chore->id)->first();
            abort_if($chore->status === 'pending_review' && $submission?->decision === null, 422, 'This chore is already waiting for review.');
            ChoreSubmission::updateOrCreate(['chore_id' => $chore->id], [
                'evidence_type' => 'checkbox', 'submitted_at' => now(),
                'decision' => null, 'decided_by' => null, 'decided_at' => null,
            ]);
            $chore->update(['status' => 'pending_review']);
        });

        return response()->json(['data' => ['chore_id' => $chore->id, 'status' => 'pending_review']], 201);
    }
}
