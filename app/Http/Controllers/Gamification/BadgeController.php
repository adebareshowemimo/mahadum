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
            ->sortByDesc('earned_at')
            ->map(fn ($lb) => [
                'code' => $lb->badge->code,
                'name' => $lb->badge->name,
                'description' => $lb->badge->description,
                'icon' => $lb->badge->icon,
                'level' => str_starts_with($lb->badge->code, 'tier_') ? (int) substr($lb->badge->code, 5) : null,
                'earned_at' => $lb->earned_at,
            ])->values();

        $earnedIds = $earned->pluck('code')->all();

        $locked = Badge::whereNotIn('code', $earnedIds ?: [''])->get()
            ->map(fn ($b) => [
                'code' => $b->code,
                'name' => $b->name,
                'description' => $b->description,
                'icon' => $b->icon,
                'level' => str_starts_with($b->code, 'tier_') ? (int) substr($b->code, 5) : null,
            ]);

        return response()->json(['data' => [
            'earned' => $earned->values(),
            'locked' => $locked->values(),
        ]]);
    }
}
