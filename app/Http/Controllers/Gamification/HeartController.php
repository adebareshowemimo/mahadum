<?php

namespace App\Http\Controllers\Gamification;

use App\Http\Controllers\Concerns\ResolvesLearner;
use App\Http\Controllers\Controller;
use App\Models\Heart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     */
    public function refill(Request $request): JsonResponse
    {
        $request->validate([
            'learner_id' => ['required', 'integer', 'exists:learner_profiles,id'],
            'method' => ['required', 'in:ad,coins'],
        ]);
        $learner = $this->learner($request->integer('learner_id'));

        $heart = Heart::firstOrCreate(['learner_profile_id' => $learner->id], ['current' => self::MAX_HEARTS]);
        $heart->update(['current' => self::MAX_HEARTS, 'refills_at' => null]);

        return response()->json(['data' => [
            'current' => $heart->current,
            'refills_at' => $heart->refills_at,
            'method' => $request->string('method'),
        ]]);
    }
}
