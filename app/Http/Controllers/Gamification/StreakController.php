<?php

namespace App\Http\Controllers\Gamification;

use App\Http\Controllers\Concerns\ResolvesLearner;
use App\Http\Controllers\Controller;
use App\Models\LearnerProfile;
use App\Models\Streak;
use App\Services\Gamification\StreakService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function shield(Request $request, StreakService $streaks): JsonResponse
    {
        $request->validate(['learner_id' => ['required', 'integer', 'exists:learner_profiles,id']]);
        $learner = $this->learner($request->integer('learner_id'));

        $protection = $streaks->armShield($learner);

        return response()->json(['data' => [
            'protection_id' => $protection->id,
            'type' => $protection->type,
            'active_to' => $protection->active_to,
            // NOTE: coin/premium payment for the shield is applied by the wallet slice.
        ]], 201);
    }
}
