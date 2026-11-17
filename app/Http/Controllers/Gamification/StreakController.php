<?php

namespace App\Http\Controllers\Gamification;

use App\Http\Controllers\Concerns\ResolvesLearner;
use App\Http\Controllers\Controller;
use App\Models\LearnerProfile;
use App\Models\Streak;
use App\Models\StreakProtection;
use App\Models\Wallet;
use App\Services\Family\WalletService;
use App\Services\Gamification\StreakService;
use App\Services\Settings;
use App\Support\ActiveChildProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StreakController extends Controller
{
    use ResolvesLearner;

    /** Authorized by route can:view,learner. */
    public function show(LearnerProfile $learner): JsonResponse
    {
        $streak = Streak::firstOrCreate(
            ['learner_profile_id' => $learner->id],
            ['current_count' => 0, 'longest_count' => 0, 'state' => 'active'],
        );

        return response()->json(['data' => [
            'count' => $streak->current_count,
            'longest' => $streak->longest_count,
            'state' => $streak->state,
            'frozen_until' => $streak->frozen_until,
        ]]);
    }

    public function shield(Request $request, StreakService $streaks, WalletService $wallets, Settings $settings): JsonResponse
    {
        $request->validate(['learner_id' => ['required', 'integer', 'exists:learner_profiles,id']]);
        abort_if(ActiveChildProfile::id($request) !== null, 403, 'Ask a grown-up to protect your streak.');
        $learner = $this->learnerForReward($request->integer('learner_id'));
        $price = (int) $settings->get('streak.shield_price_coins');
        abort_unless($price > 0, 422, 'Streak Shield purchases are not available yet.');

        $protection = DB::transaction(function () use ($learner, $price, $streaks, $wallets) {
            $learner = LearnerProfile::whereKey($learner->id)->lockForUpdate()->firstOrFail();
            abort_if(StreakProtection::where('learner_profile_id', $learner->id)->where('consumed', false)
                ->where('active_to', '>=', now())->exists(), 422, 'A Streak Shield is already active.');
            $owner = $learner->family ?? $learner->user;
            abort_unless($owner !== null, 422, 'A wallet is required to buy a Streak Shield.');
            $wallet = $wallets->walletFor($owner);
            $wallet = Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            abort_unless($wallet->coin_balance >= $price, 422, 'Not enough coins. Add coins to your wallet first.');
            $protection = $streaks->armShield($learner, 'coin_purchase');
            $wallets->debit($wallet, $price, 'streak_shield', $learner->id, $protection);

            return $protection;
        });

        return response()->json(['data' => [
            'protection_id' => $protection->id,
            'type' => $protection->type,
            'active_to' => $protection->active_to,
            'coins_spent' => $price,
        ]], 201);
    }
}
