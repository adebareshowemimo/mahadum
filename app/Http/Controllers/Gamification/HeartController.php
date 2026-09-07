<?php

namespace App\Http\Controllers\Gamification;

use App\Http\Controllers\Concerns\ResolvesLearner;
use App\Http\Controllers\Controller;
use App\Models\AdImpression;
use App\Models\Heart;
use App\Models\LearnerProfile;
use App\Services\Billing\EntitlementResolver;
use App\Services\Gamification\PracticeModeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class HeartController extends Controller
{
    use ResolvesLearner;

    public function show(Request $request, PracticeModeService $practice): JsonResponse
    {
        $request->validate(['learner_id' => ['required', 'integer', 'exists:learner_profiles,id']]);
        $learner = $this->learner($request->integer('learner_id'));

        if (app(EntitlementResolver::class)->forLearner($learner)['unlimited_hearts']) {
            return response()->json(['data' => ['current' => null, 'unlimited_hearts' => true, 'practice_mode' => false, 'competitive_paused_until' => null, 'refills_at' => null]]);
        }
        $state = $practice->state($learner);
        $heart = Heart::where('learner_profile_id', $learner->id)->firstOrFail();

        return response()->json(['data' => [
            ...$state,
            'refills_at' => $heart->refills_at,
        ]]);
    }

    /**
     * Refill through a verified rewarded ad. Coin refills remain unavailable until
     * a coin price and ledger debit are configured; they cannot bypass the lock for free.
     *
     * The `ad` method requires a specific, already-verified-shown AdImpression
     * (from AdController@complete) for this learner's `rewarded_heart`
     * placement, consumed exactly once — closing the gap where a client could
     * previously claim a refill by just asserting `method: ad` with nothing to
     * back it up.
     */
    public function refill(Request $request): JsonResponse
    {
        $request->validate([
            'learner_id' => ['required', 'integer', 'exists:learner_profiles,id'],
            'method' => ['required', 'in:ad,coins'],
            'ad_impression_id' => ['required_if:method,ad', 'integer', 'exists:ad_impressions,id'],
        ]);
        $learner = $this->learner($request->integer('learner_id'));
        $method = $request->string('method')->value();

        abort_if($method === 'coins', 422, 'Coin refills are not configured. Watch an eligible ad, wait for your refill, or upgrade.');

        return DB::transaction(function () use ($request, $learner, $method) {
            LearnerProfile::whereKey($learner->id)->lockForUpdate()->firstOrFail();
            if ($method === 'ad') {
                // Redeeming a reward is self/parent only — narrower than the
                // same-tenant-staff view access used to resolve $learner above.
                Gate::authorize('redeemReward', $learner);

                $impression = AdImpression::lockForUpdate()->findOrFail($request->integer('ad_impression_id'));
                abort_unless((int) $impression->learner_profile_id === $learner->id, 403, 'This ad was not requested for this learner.');
                abort_unless($impression->placement === 'rewarded_heart', 422, 'This ad was not for a hearts refill.');
                abort_unless($impression->shown_at !== null, 422, 'This ad has not been verified as shown yet.');
                abort_if($impression->consumed_at !== null, 422, 'This ad has already been redeemed.');
                $impression->update(['consumed_at' => now()]);
            }

            $heart = Heart::firstOrCreate(['learner_profile_id' => $learner->id], ['current' => PracticeModeService::MAX_HEARTS]);
            $heart->update([
                'current' => PracticeModeService::MAX_HEARTS,
                'questions_since_loss' => 0,
                'refills_at' => null,
                'competitive_paused_until' => null,
            ]);

            return response()->json(['data' => [
                'current' => $heart->current,
                'refills_at' => $heart->refills_at,
                'method' => $method,
                'practice_mode' => false,
                'competitive_paused_until' => null,
            ]]);
        });
    }
}
