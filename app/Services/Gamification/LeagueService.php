<?php

namespace App\Services\Gamification;

use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\LearnerProfile;
use App\Models\XpLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Weekly leagues of at most 30 distinct learners. Existing current-week
 * membership IDs and XP ledgers are retained when oversized groups are split.
 * Tier promotion/relegation is not introduced by cohort assignment.
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

        return Cache::lock('league-cohort:'.$weekStart->toDateString(), 30)->block(10, fn () => DB::transaction(function () use ($weekStart, $learner) {
            $leagues = League::whereDate('week_start', $weekStart)->where('tier', 1)->orderBy('id')->lockForUpdate()->get();
            if ($leagues->isEmpty()) {
                $leagues->push(League::create(['week_start' => $weekStart->toDateString(), 'tier' => 1, 'name' => 'Week of '.$weekStart->toDateString().' · Group 1']));
            }
            $members = LeagueMembership::whereIn('league_id', $leagues->pluck('id'))->orderBy('id')->lockForUpdate()->get();
            $learners = $members->unique('learner_profile_id')->pluck('learner_profile_id');
            foreach ($learners->chunk(30)->values() as $index => $chunk) {
                if (! isset($leagues[$index])) {
                    $leagues->push(League::create(['week_start' => $weekStart->toDateString(), 'tier' => 1, 'name' => 'Week of '.$weekStart->toDateString().' · Group '.($index + 1)]));
                }
                LeagueMembership::whereIn('id', $members->whereIn('learner_profile_id', $chunk)->pluck('id'))->update(['league_id' => $leagues[$index]->id]);
            }
            if ($existing = $members->firstWhere('learner_profile_id', $learner->id)) {
                return $existing->refresh();
            }
            $index = intdiv($learners->count(), 30);
            if (! isset($leagues[$index])) {
                $leagues->push(League::create(['week_start' => $weekStart->toDateString(), 'tier' => 1, 'name' => 'Week of '.$weekStart->toDateString().' · Group '.($index + 1)]));
            }

            return LeagueMembership::create(['league_id' => $leagues[$index]->id, 'learner_profile_id' => $learner->id, 'weekly_xp' => 0]);
        }));
    }

    /** Recompute weekly_xp for every member of the league, then rank them. */
    public function refreshAndRank(League $league): Collection
    {
        $weekStart = Carbon::parse($league->week_start)->startOfDay();

        // A learner can have legacy duplicate membership rows. Rank each profile
        // once, keeping distinct profiles even when their display names match.
        // Preserve the underlying records; XP still comes from that profile's ledger.
        $memberships = $league->memberships()->with('learnerProfile')->orderBy('id')->get()
            ->unique('learner_profile_id')->values();

        foreach ($memberships as $membership) {
            $xp = XpLedger::where('learner_profile_id', $membership->learner_profile_id)
                ->where('created_at', '>=', $weekStart)
                ->where('created_at', '<', $weekStart->copy()->addWeek())
                ->sum('amount');
            $membership->weekly_xp = max(0, (int) $xp);
        }

        $ranked = $memberships->sortBy([['weekly_xp', 'desc'], ['learner_profile_id', 'asc']])->values();

        $ranked->each(function ($membership, $i) {
            $membership->rank = $i + 1;
            $membership->save();
        });

        return $ranked;
    }
}
