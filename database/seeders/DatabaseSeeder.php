<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            PlanSeeder::class,      // billing tiers (free / premium / school_*)
            LanguageSeeder::class,  // yo / ig / ha / pcm
            BadgeSeeder::class,     // gamification badges
        ]);

        // Demo fixtures for local/QA only — never in production.
        if (app()->environment('local', 'demo')) {
            $this->call(DemoSeeder::class);
        }
    }
}
