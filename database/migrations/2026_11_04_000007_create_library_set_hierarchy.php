<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_sets', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);
            $table->foreignId('language_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->index(['kind', 'status']);
        });
        Schema::create('library_set_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_set_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('position');
            $table->boolean('is_free')->default(true);
            $table->timestamps();
            $table->unique(['library_set_id', 'position']);
        });
        Schema::table('lessons', function (Blueprint $table) {
            $table->foreignId('library_set_level_id')->nullable()->constrained('library_set_levels')->nullOnDelete();
        });

        // Each existing standalone item was a set. Keep its identity and lesson ID.
        foreach (DB::table('lessons')->whereIn('kind', ['proverb', 'trivia'])->whereNull('deleted_at')->orderBy('id')->get() as $lesson) {
            $number = max(1, (int) $lesson->standalone_level);
            $setId = DB::table('library_sets')->insertGetId([
                'kind' => $lesson->kind, 'language_id' => $lesson->language_id,
                'title' => $lesson->title, 'description' => null,
                'status' => $lesson->published_at ? 'published' : 'draft',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $levelId = DB::table('library_set_levels')->insertGetId([
                'library_set_id' => $setId, 'title' => 'Level '.$number, 'position' => $number,
                'is_free' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('lessons')->where('id', $lesson->id)->update(['library_set_level_id' => $levelId]);
        }
    }

    public function down(): void
    {
        Schema::table('lessons', fn (Blueprint $table) => $table->dropConstrainedForeignId('library_set_level_id'));
        Schema::dropIfExists('library_set_levels');
        Schema::dropIfExists('library_sets');
    }
};
