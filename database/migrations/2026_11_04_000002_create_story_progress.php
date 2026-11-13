<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps();
            $table->unique(['learner_profile_id', 'story_id']);
            $table->index(['learner_profile_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_progress');
    }
};
