<?php

namespace App\Http\Controllers\Gamification;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\LearnerBadge;
use App\Models\LearnerProfile;
use Illuminate\Http\JsonResponse;

class BadgeController extends Controller
{
    /** Earned + locked badges for a learner. Authorized by route can:view,learner. */
    public function index(LearnerProfile $learner): JsonResponse
    {
        $earned = LearnerBadge::where('learner_profile_id', $learner->id)
            ->with('badge')->get()
            ->map(fn ($lb) => [
                'code' => $lb->badge->code,
                'name' => $lb->badge->name,
                'earned_at' => $lb->earned_at,
            ]);

        $earnedIds = $earned->pluck('code')->all();

        $locked = Badge::whereNotIn('code', $earnedIds ?: [''])->get()
            ->map(fn ($b) => ['code' => $b->code, 'name' => $b->name, 'description' => $b->description]);

        return response()->json(['data' => [
            'earned' => $earned->values(),
            'locked' => $locked->values(),
        ]]);
    }
}
