<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            ['code' => 'first_lesson',  'name' => 'First Steps',     'description' => 'Completed your first lesson.'],
            ['code' => 'streak_7',      'name' => 'Week Warrior',    'description' => 'Kept a 7-day streak.'],
            ['code' => 'sharp_shooter', 'name' => 'Sharp Shooter',   'description' => 'Scored 100% on a quiz.'],
            ['code' => 'family_hero',   'name' => 'Family Hero',    'description' => 'Earned the most legitimate XP in your family for a day.'],
        ];

        foreach ($badges as $badge) {
            Badge::updateOrCreate(['code' => $badge['code']], $badge);
        }
    }
}
