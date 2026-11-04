<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_reward_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->date('study_date');
            $table->unsignedSmallInteger('progress_seconds')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->string('status')->default('studying');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['learner_profile_id', 'study_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_reward_days');
    }
};
