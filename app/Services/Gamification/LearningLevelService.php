<?php

namespace App\Services\Gamification;

use App\Models\LearnerProfile;

class LearningLevelService
{
    /** @var array<int, array{name:string, min_xp:int}> */
    public const LEVELS = [
        0 => ['name' => 'Star Starter', 'min_xp' => 0],
        1 => ['name' => 'Bronze', 'min_xp' => 100],
        2 => ['name' => 'Silver', 'min_xp' => 500],
        3 => ['name' => 'Gold', 'min_xp' => 1500],
        4 => ['name' => 'Platinum', 'min_xp' => 4000],
        5 => ['name' => 'Culture Master', 'min_xp' => 10000],
    ];

    /** @return array{number:int, name:string, lifetime_xp:int, next_level_xp:int|null} */
    public function forLearner(LearnerProfile $learner, bool $sync = true): array
    {
        $xp = max(0, (int) $learner->xpEntries()->sum('amount'));
        $number = $this->numberForXp($xp);

        if ($sync && (int) $learner->current_level !== $number) {
            $learner->update(['current_level' => $number]);
        }

        return [
            'number' => $number,
            'name' => self::LEVELS[$number]['name'],
            'lifetime_xp' => $xp,
            'next_level_xp' => self::LEVELS[$number + 1]['min_xp'] ?? null,
        ];
    }

    public function numberForXp(int $xp): int
    {
        foreach (array_reverse(self::LEVELS, true) as $number => $level) {
            if ($xp >= $level['min_xp']) {
                return $number;
            }
        }

        return 0;
    }
}
