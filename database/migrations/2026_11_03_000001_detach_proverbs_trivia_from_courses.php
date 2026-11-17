<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->foreignId('language_id')->nullable()->constrained('languages')->nullOnDelete();
            $table->unsignedInteger('standalone_level')->nullable();
        });

        DB::table('lessons')->whereIn('kind', ['proverb', 'trivia'])
            ->whereNotNull('course_level_id')->orderBy('id')->chunkById(100, function ($lessons) {
                foreach ($lessons as $lesson) {
                    $level = DB::table('course_levels')->where('id', $lesson->course_level_id)->first();
                    $course = $level ? DB::table('courses')->where('id', $level->course_id)->first() : null;
                    DB::table('lessons')->where('id', $lesson->id)->update([
                        'language_id' => $course?->language_id,
                        'standalone_level' => $level ? max(1, min(10, (int) $level->position)) : 1,
                    ]);
                }
            });

        Schema::table('lessons', fn (Blueprint $table) => $table->foreignId('course_level_id')->nullable()->change());
        DB::table('lessons')->whereIn('kind', ['proverb', 'trivia'])->update(['course_level_id' => null]);
    }

    public function down(): void
    {
        // Detached sets cannot be placed back into a course without inventing ownership.
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('language_id');
            $table->dropColumn('standalone_level');
        });
    }
};
