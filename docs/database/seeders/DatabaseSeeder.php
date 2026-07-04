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
        ]);
    }
}
