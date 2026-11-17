<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->foreignId('language_id')->nullable()->constrained('languages')->restrictOnDelete();
            $table->unsignedInteger('standalone_level')->default(1);
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        DB::table('stories')->whereNotNull('course_id')->orderBy('id')->chunkById(100, function ($stories) {
            foreach ($stories as $story) {
                $course = DB::table('courses')->where('id', $story->course_id)->first();
                DB::table('stories')->where('id', $story->id)->update([
                    'language_id' => $course?->language_id,
                    'owner_user_id' => $course?->owner_user_id,
                ]);
            }
        });

        Schema::table('stories', fn (Blueprint $table) => $table->foreignId('course_id')->nullable()->change());
        DB::table('stories')->update(['course_id' => null]);
    }

    public function down(): void
    {
        throw new RuntimeException('Detached stories cannot be assigned to courses automatically.');
    }
};
