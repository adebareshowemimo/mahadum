<?php

namespace App\Services\Gamification;

use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\LearnerProfile;
use App\Models\XpLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Weekly leagues. For this slice everyone joins a single tier-1 league for the
 * current week; weekly_xp is recomputed from the xp_ledger on read and used to
 * rank members. The 30-learner bucketing + tier promotion/relegation is a
 * scheduled-job concern (EvaluateStreaks / league rollover) added later.
 */
class LeagueService
{
    public function currentWeekStart(): Carbon
    {
        return Carbon::now()->startOfWeek(); // Monday
    }

    public function ensureMembership(LearnerProfile $learner): LeagueMembership
    {
        $weekStart = $this->currentWeekStart();

        $league = League::firstOrCreate(
            ['week_start' => $weekStart->toDateString(), 'tier' => 1],
            ['name' => 'Week of '.$weekStart->toDateString()],
        );

        return LeagueMembership::firstOrCreate(
            ['league_id' => $league->id, 'learner_profile_id' => $learner->id],
            ['weekly_xp' => 0],
        );
    }

    /** Recompute weekly_xp for every member of the league, then rank them. */
    public function refreshAndRank(League $league): Collection
    {
        $weekStart = Carbon::parse($league->week_start)->startOfDay();

        $memberships = $league->memberships()->with('learnerProfile')->get();

        foreach ($memberships as $membership) {
            $xp = XpLedger::where('learner_profile_id', $membership->learner_profile_id)
                ->where('created_at', '>=', $weekStart)
                ->sum('amount');
            $membership->weekly_xp = max(0, (int) $xp);
        }

        $ranked = $memberships->sortByDesc('weekly_xp')->values();

        $ranked->each(function ($membership, $i) {
            $membership->rank = $i + 1;
            $membership->save();
        });

        return $ranked;
    }
}
