<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            ['code' => 'first_lesson',  'name' => 'First Steps',     'description' => 'Completed your first lesson.',      'icon' => '👣'],
            ['code' => 'streak_7',      'name' => 'Week Warrior',    'description' => 'Kept a 7-day streak.',              'icon' => '🔥'],
            ['code' => 'sharp_shooter', 'name' => 'Sharp Shooter',   'description' => 'Scored 100% on a quiz.',            'icon' => '🎯'],
            ['code' => 'family_hero',   'name' => 'Family Hero',     'description' => 'Earned the most legitimate XP in your family for a day.', 'icon' => '⭐'],

            // Learning-tier badges (one per level — see LearningLevelService).
            ['code' => 'tier_0', 'name' => 'Star Starter',   'description' => 'Awarded for successfully completing Level 0 content.', 'icon' => '⭐'],
            ['code' => 'tier_1', 'name' => 'Bronze',         'description' => 'Awarded for successfully completing Level 1 content.',   'icon' => '🥉'],
            ['code' => 'tier_2', 'name' => 'Silver',         'description' => 'Awarded for successfully completing Level 2 content.',   'icon' => '🥈'],
            ['code' => 'tier_3', 'name' => 'Gold',           'description' => 'Awarded for successfully completing Level 3 content.', 'icon' => '🥇'],
            ['code' => 'tier_4', 'name' => 'Platinum',       'description' => 'Awarded for successfully completing Level 4 content.', 'icon' => '💎'],
            ['code' => 'tier_5', 'name' => 'Culture Master', 'description' => 'Awarded for successfully completing Level 5 content.', 'icon' => '👑'],
        ];

        foreach ($badges as $badge) {
            Badge::updateOrCreate(['code' => $badge['code']], $badge);
        }
    }
}
