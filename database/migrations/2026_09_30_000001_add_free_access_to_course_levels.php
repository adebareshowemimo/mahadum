<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_levels', fn (Blueprint $table) => $table->boolean('is_free')->default(false));
        // Preserve legacy free previews and explicitly named introductory levels.
        // Position is an ordering value; titles are used only for this one-time backfill.
        $legacyIds = DB::table('lessons')->where('is_free_preview', true)->whereNull('deleted_at')->pluck('course_level_id');
        DB::table('course_levels')->whereIn('id', $legacyIds)->orWhere('position', 0)->update(['is_free' => true]);
        foreach (DB::table('course_levels')->select('id', 'title')->cursor() as $level) {
            if (preg_match('/^level\s*0\b/iu', trim($level->title))) {
                DB::table('course_levels')->where('id', $level->id)->update(['is_free' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('course_levels', fn (Blueprint $table) => $table->dropColumn('is_free'));
    }
};
