<?php

namespace App\Http\Controllers\Gamification;

use App\Http\Controllers\Concerns\ResolvesLearner;
use App\Http\Controllers\Controller;
use App\Models\League;
use App\Services\Gamification\LeagueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    use ResolvesLearner;

    public function __construct(private LeagueService $leagues) {}

    /** The caller's learner's current-week league standing. */
    public function current(Request $request): JsonResponse
    {
        $request->validate(['learner_id' => ['required', 'integer', 'exists:learner_profiles,id']]);
        $learner = $this->learner($request->integer('learner_id'));

        $membership = $this->leagues->ensureMembership($learner);
        $league = $membership->league;
        $ranked = $this->leagues->refreshAndRank($league);
        $mine = $ranked->firstWhere('learner_profile_id', $learner->id);

        return response()->json(['data' => [
            'league' => ['id' => $league->id, 'name' => $league->name, 'tier' => $league->tier, 'week_start' => $league->week_start],
            'rank' => $mine?->rank,
            'weekly_xp' => $mine?->weekly_xp,
        ]]);
    }

    /** Ranked members of a league (defaults to the learner's current league). */
    public function index(Request $request): JsonResponse
    {
        $league = null;

        if ($request->filled('league')) {
            $league = League::findOrFail($request->integer('league'));
        } elseif ($request->filled('learner_id')) {
            $request->validate(['learner_id' => ['integer', 'exists:learner_profiles,id']]);
            $league = $this->leagues->ensureMembership($this->learner($request->integer('learner_id')))->league;
        } else {
            abort(422, 'Provide a league or learner_id.');
        }

        $ranked = $this->leagues->refreshAndRank($league);

        return response()->json(['data' => $ranked->map(fn ($m) => [
            'rank' => $m->rank,
            'learner_id' => $m->learner_profile_id,
            'display_name' => $m->learnerProfile?->display_name,
            'weekly_xp' => $m->weekly_xp,
        ])->values()]);
    }
}
