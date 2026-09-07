<?php

use Database\Seeders\BadgeSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', fn (Blueprint $table) => $table->boolean('is_free_preview')->default(false));
        Schema::table('question_responses', fn (Blueprint $table) => $table->unsignedInteger('xp_awarded')->default(0));
        Schema::table('hearts', fn (Blueprint $table) => $table->unsignedTinyInteger('questions_since_loss')->default(0));

        (new BadgeSeeder)->run();

        // Pin one introductory Lesson 0 per language. Reordering content later
        // must not silently move a customer's free entitlement to another lesson.
        foreach (DB::table('languages')->pluck('id') as $languageId) {
            $lessonId = DB::table('lessons')->join('course_levels', 'course_levels.id', '=', 'lessons.course_level_id')
                ->join('courses', 'courses.id', '=', 'course_levels.course_id')
                ->where('courses.language_id', $languageId)->where('courses.is_published', true)->whereNull('lessons.deleted_at')
                ->whereNull('courses.deleted_at')->whereNotNull('lessons.published_at')
                ->orderBy('courses.id')->orderBy('course_levels.position')->orderBy('lessons.position')->orderBy('lessons.id')
                ->value('lessons.id');
            if ($lessonId !== null) {
                DB::table('lessons')->where('id', $lessonId)->update(['is_free_preview' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('lessons', fn (Blueprint $table) => $table->dropColumn('is_free_preview'));
        Schema::table('question_responses', fn (Blueprint $table) => $table->dropColumn('xp_awarded'));
        Schema::table('hearts', fn (Blueprint $table) => $table->dropColumn('questions_since_loss'));
    }
};
