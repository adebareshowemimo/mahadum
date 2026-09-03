<?php

namespace App\Console\Commands;

use App\Models\Badge;
use App\Models\Family;
use App\Models\FamilyHeroAward;
use App\Models\LearnerBadge;
use App\Models\XpLedger;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class AwardFamilyHeroes extends Command
{
    protected $signature = 'gamification:award-family-heroes {--date= : Family-local date (Y-m-d), defaults to yesterday}';

    protected $description = 'Award the daily Family Hero badge to every top-XP learner in each family';

    public function handle(): int
    {
        $badge = Badge::firstOrCreate(
            ['code' => 'family_hero'],
            ['name' => 'Family Hero', 'description' => 'Earned the most legitimate XP in your family for a day.'],
        );
        $awarded = 0;

        Family::with('learnerProfiles')->chunkById(100, function ($families) use ($badge, &$awarded): void {
            foreach ($families as $family) {
                if ($family->learnerProfiles->isEmpty()) {
                    continue;
                }

                try {
                    $timezone = new \DateTimeZone($family->timezone ?: 'Africa/Lagos');
                } catch (\Exception) {
                    $timezone = new \DateTimeZone('Africa/Lagos');
                }

                $day = $this->option('date')
                    ? CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->option('date'), $timezone)
                    : CarbonImmutable::now($timezone)->subDay()->startOfDay();

                if ($day === null) {
                    $this->error('The --date value must use Y-m-d.');

                    return;
                }

                $start = $day->startOfDay()->utc();
                $end = $day->addDay()->startOfDay()->utc();
                $totals = XpLedger::query()
                    ->whereIn('learner_profile_id', $family->learnerProfiles->pluck('id'))
                    ->where('created_at', '>=', $start)
                    ->where('created_at', '<', $end)
                    ->selectRaw('learner_profile_id, SUM(amount) as xp_earned')
                    ->groupBy('learner_profile_id')
                    ->get();
                $topXp = (int) $totals->max('xp_earned');

                if ($topXp <= 0) {
                    continue;
                }

                foreach ($totals->where('xp_earned', $topXp) as $winner) {
                    $award = FamilyHeroAward::where('family_id', $family->id)
                        ->where('learner_profile_id', $winner->learner_profile_id)
                        ->whereDate('award_date', $day->toDateString())
                        ->first();
                    if ($award === null) {
                        $award = FamilyHeroAward::create([
                            'family_id' => $family->id,
                            'learner_profile_id' => $winner->learner_profile_id,
                            'award_date' => $day->toDateString(),
                            'xp_earned' => $topXp,
                        ]);
                    }

                    LearnerBadge::firstOrCreate([
                        'learner_profile_id' => $winner->learner_profile_id,
                        'badge_id' => $badge->id,
                    ], ['earned_at' => now()]);

                    $awarded += $award->wasRecentlyCreated ? 1 : 0;
                }
            }
        });

        $this->info("Awarded {$awarded} Family Hero record(s).");

        return self::SUCCESS;
    }
}
