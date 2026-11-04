<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('badges')->updateOrInsert(['code' => 'streak_30'], [
            'name' => 'Month Maker', 'description' => 'Kept a 30-day streak.',
            'icon' => '🔥', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('badges')->where('code', 'streak_30')->delete();
    }
};
