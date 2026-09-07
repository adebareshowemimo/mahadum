<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Feedback (Sept 4, §4): the six learning tiers each get a real badge row so
 * they can be awarded, notified, and shown with an earned date + description
 * (BadgeService / LearningLevelService). Idempotent upsert — safe on a database
 * that already ran BadgeSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tiers = [
            ['code' => 'tier_0', 'name' => 'Star Starter',   'description' => 'Reached Level 0 — your learning journey has begun.', 'icon' => '⭐'],
            ['code' => 'tier_1', 'name' => 'Bronze',         'description' => 'Reached Level 1 by earning 100 XP.',   'icon' => '🥉'],
            ['code' => 'tier_2', 'name' => 'Silver',         'description' => 'Reached Level 2 by earning 500 XP.',   'icon' => '🥈'],
            ['code' => 'tier_3', 'name' => 'Gold',           'description' => 'Reached Level 3 by earning 1,500 XP.', 'icon' => '🥇'],
            ['code' => 'tier_4', 'name' => 'Platinum',       'description' => 'Reached Level 4 by earning 4,000 XP.', 'icon' => '💎'],
            ['code' => 'tier_5', 'name' => 'Culture Master', 'description' => 'Reached Level 5 by earning 10,000 XP.', 'icon' => '👑'],
        ];

        foreach ($tiers as $tier) {
            DB::table('badges')->updateOrInsert(
                ['code' => $tier['code']],
                array_merge($tier, ['updated_at' => now(), 'created_at' => now()]),
            );
        }
    }

    public function down(): void
    {
        DB::table('badges')->whereIn('code', ['tier_0', 'tier_1', 'tier_2', 'tier_3', 'tier_4', 'tier_5'])->delete();
    }
};
