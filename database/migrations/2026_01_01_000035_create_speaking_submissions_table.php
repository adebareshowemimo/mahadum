<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('speaking_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_profile_id')->constrained('learner_profiles')->cascadeOnDelete();
            $table->foreignId('lesson_component_id')->constrained('lesson_components')->cascadeOnDelete();
            $table->foreignId('audio_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->decimal('ai_score', 12, 2)->nullable();
            $table->decimal('tone_accuracy', 12, 2)->nullable();
            $table->string('status')->default('needs_review');
            $table->boolean('reviewed_by_parent')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('speaking_submissions');
    }
};
