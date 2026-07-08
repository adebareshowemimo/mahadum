<?php

namespace App\Http\Controllers\Gamification;

use App\Http\Controllers\Concerns\ResolvesLearner;
use App\Http\Controllers\Controller;
use App\Models\AdImpression;
use App\Models\Heart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class HeartController extends Controller
{
    use ResolvesLearner;

    private const MAX_HEARTS = 5;

    public function show(Request $request): JsonResponse
    {
        $request->validate(['learner_id' => ['required', 'integer', 'exists:learner_profiles,id']]);
        $learner = $this->learner($request->integer('learner_id'));

        $heart = Heart::firstOrCreate(['learner_profile_id' => $learner->id], ['current' => self::MAX_HEARTS]);

        return response()->json(['data' => ['current' => $heart->current, 'refills_at' => $heart->refills_at]]);
    }

    /**
     * Refill via rewarded ad or coins (Rule 4 — hearts never block learning, so
     * this is a convenience top-up). Coin deduction is applied by the wallet slice.
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

        if ($method === 'ad') {
            // Redeeming a reward is self/parent only — narrower than the
            // same-tenant-staff view access used to resolve $learner above.
            Gate::authorize('redeemReward', $learner);

            $impression = AdImpression::findOrFail($request->integer('ad_impression_id'));
            abort_unless((int) $impression->learner_profile_id === $learner->id, 403, 'This ad was not requested for this learner.');
            abort_unless($impression->placement === 'rewarded_heart', 422, 'This ad was not for a hearts refill.');
            abort_unless($impression->shown_at !== null, 422, 'This ad has not been verified as shown yet.');
            abort_if($impression->consumed_at !== null, 422, 'This ad has already been redeemed.');
            $impression->update(['consumed_at' => now()]);
        }

        $heart = Heart::firstOrCreate(['learner_profile_id' => $learner->id], ['current' => self::MAX_HEARTS]);
        $heart->update(['current' => self::MAX_HEARTS, 'refills_at' => null]);

        return response()->json(['data' => [
            'current' => $heart->current,
            'refills_at' => $heart->refills_at,
            'method' => $method,
        ]]);
    }
}
