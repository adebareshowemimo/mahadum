<?php

namespace App\Http\Controllers\Family;

use App\Http\Controllers\Concerns\ResolvesFamily;
use App\Http\Controllers\Controller;
use App\Http\Requests\Family\ReviewChoreRequest;
use App\Http\Requests\Family\StoreChoreRequest;
use App\Models\Chore;
use App\Models\ChoreSubmission;
use App\Models\LearnerProfile;
use App\Services\Family\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChoreController extends Controller
{
    use ResolvesFamily;

    public function __construct(private WalletService $wallets) {}

    public function index(Request $request): JsonResponse
    {
        $family = $this->family($request->user());

        $chores = $family->chores()->with('assigneeLearnerProfile')->latest()->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'status' => $c->status,
                'coin_reward' => $c->coin_reward,
                'assignee' => $c->assigneeLearnerProfile?->display_name,
                'due_at' => $c->due_at,
            ]);

        return response()->json(['data' => $chores]);
    }

    public function store(StoreChoreRequest $request): JsonResponse
    {
        $family = $this->family($request->user());

        $assigneeInFamily = LearnerProfile::where('family_id', $family->id)
            ->whereKey($request->integer('assignee_learner_profile_id'))->exists();
        abort_unless($assigneeInFamily, 422, 'Assignee is not in your family.');

        $chore = $family->chores()->create([
            'created_by_user_id' => $request->user()->id,
            'assignee_learner_profile_id' => $request->integer('assignee_learner_profile_id'),
            'title' => $request->string('title'),
            'description' => $request->input('description'),
            'coin_reward' => $request->integer('coin_reward'),
            'due_at' => $request->input('due_at'),
            'status' => 'active',
        ]);

        return response()->json(['data' => ['id' => $chore->id, 'status' => $chore->status]], 201);
    }

    /**
     * Parent review. Coins are released to the child's wallet ONLY on approval
     * (Rule 8) — done atomically with the decision + status change.
     */
    public function review(ReviewChoreRequest $request, Chore $chore): JsonResponse
    {
        abort_unless($chore->family_id === $this->family($request->user())->id, 403, 'Not your family chore.');

        $decision = $request->string('decision')->value();

        $result = DB::transaction(function () use ($request, $chore, $decision) {
            ChoreSubmission::updateOrCreate(
                ['chore_id' => $chore->id],
                ['decision' => $decision, 'decided_by' => $request->user()->id, 'decided_at' => now()],
            );

            $coinsReleased = 0;
            if ($decision === 'approve') {
                $chore->update(['status' => 'approved']);
                if ($chore->coin_reward > 0 && $chore->assignee_learner_profile_id) {
                    $learner = LearnerProfile::find($chore->assignee_learner_profile_id);
                    $this->wallets->credit(
                        $this->wallets->walletFor($learner),
                        $chore->coin_reward,
                        'chore',
                        $learner->id,
                        $chore,
                    );
                    $coinsReleased = $chore->coin_reward;
                }
            } elseif ($decision === 'reject') {
                $chore->update(['status' => 'rejected']);
            } else { // more_evidence
                $chore->update(['status' => 'pending_review']);
            }

            return $coinsReleased;
        });

        return response()->json(['data' => [
            'chore_id' => $chore->id,
            'status' => $chore->fresh()->status,
            'coins_released' => $result,
        ]]);
    }
}
