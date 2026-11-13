<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Concerns\ResolvesLearner;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\StudyRewardDay;
use App\Notifications\FamilyActivityAlert;
use App\Services\Family\WalletService;
use App\Services\Learning\LessonAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StudyRewardController extends Controller
{
    use ResolvesLearner;

    public function heartbeat(Request $request, LessonAccess $access, WalletService $wallets): JsonResponse
    {
        $values = $request->validate([
            'learner_id' => ['required', 'integer', 'exists:learner_profiles,id'],
            'lesson_id' => ['required', 'integer', 'exists:lessons,id'],
        ]);
        $learner = $this->learner($values['learner_id']);
        Gate::authorize('redeemReward', $learner);
        abort_unless($learner->family_id !== null && $learner->user_id !== $request->user()->id, 422,
            'Study coins are for child profiles in a family.');
        $access->authorize($learner, Lesson::findOrFail($values['lesson_id']));

        $reward = DB::transaction(function () use ($learner, $wallets) {
            $today = now()->toDateString();
            StudyRewardDay::firstOrCreate(['learner_profile_id' => $learner->id, 'study_date' => $today],
                ['family_id' => $learner->family_id, 'progress_seconds' => 0, 'status' => 'studying']);
            $reward = StudyRewardDay::where('learner_profile_id', $learner->id)->whereDate('study_date', $today)
                ->lockForUpdate()->firstOrFail();
            abort_unless($reward->family_id === $learner->family_id, 409, 'This learner’s family changed. Ask a parent to review the study reward.');
            if ($reward->status !== 'paid') {
                $elapsed = $reward->last_seen_at ? max(0, $reward->last_seen_at->diffInSeconds(now())) : 0;
                $reward->progress_seconds = min(300, $reward->progress_seconds + min(30, (int) $elapsed));
                $reward->last_seen_at = now();
                if ($reward->progress_seconds >= 300) {
                    try {
                        $wallets->rewardFromParent($learner, 10, 'study', $reward);
                        $reward->status = 'paid';
                        $reward->paid_at = now();
                    } catch (HttpException $error) {
                        if ($error->getStatusCode() !== 422 || ! str_contains($error->getMessage(), 'Not enough family coins')) {
                            throw $error;
                        }
                        $reward->status = 'pending';
                    }
                }
                $reward->save();
            }

            return $reward;
        });
        if ($reward->status === 'pending' && $reward->wasChanged('status')) {
            rescue(fn () => $learner->family?->owner?->notify(new FamilyActivityAlert(
                'study_reward_pending', 'Study reward waiting',
                'Your child earned 10 coins from study, but the family coin wallet is short. Check your wallet.', '/wallet',
            )), report: true);
        }

        return response()->json(['data' => [
            'status' => $reward->status,
            'progress_seconds' => $reward->progress_seconds,
            'remaining_seconds' => max(0, 300 - $reward->progress_seconds),
            'reward_coins' => 10,
        ]]);
    }
}
