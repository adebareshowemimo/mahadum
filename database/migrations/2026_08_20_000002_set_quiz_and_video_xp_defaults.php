<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('lesson_components')->where('type', 'quiz')->update(['xp_value' => 1]);
        DB::table('lesson_components')->where('type', 'video')->update(['xp_value' => 5]);
    }

    public function down(): void
    {
        // Previous quiz XP values were content-specific and cannot be restored safely.
    }
};
