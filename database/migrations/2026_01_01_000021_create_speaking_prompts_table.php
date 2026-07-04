<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('speaking_prompts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_component_id')->constrained('lesson_components')->cascadeOnDelete();
            $table->string('prompt_text');
            $table->string('target_text')->nullable();
            $table->foreignId('target_audio_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->json('tone_targets')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('speaking_prompts');
    }
};
