<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_videos', function (Blueprint $table) {
            $table->id();
            $table->string('section', 20);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('language', 20)->nullable();
            $table->string('source_type', 20);
            $table->string('youtube_id', 20)->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(false);
            $table->timestamps();
            $table->index(['section', 'is_visible', 'sort_order']);
        });

        Schema::create('guide_faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(false);
            $table->timestamps();
            $table->index(['is_visible', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_faqs');
        Schema::dropIfExists('guide_videos');
    }
};
