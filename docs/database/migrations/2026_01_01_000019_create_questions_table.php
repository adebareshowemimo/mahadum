<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->string('type');
            $table->text('prompt');
            $table->foreignId('prompt_audio_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->foreignId('prompt_image_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->text('explanation')->nullable();
            $table->string('target_text')->nullable();
            $table->json('tone_marks')->nullable();
            $table->unsignedInteger('difficulty')->default(1);
            $table->unsignedInteger('points')->default(1);
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
