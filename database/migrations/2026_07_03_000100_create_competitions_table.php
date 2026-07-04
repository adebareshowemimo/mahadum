<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The annual Language & Culture competition. Global (no tenant column):
        // it is national — "open to Nigerians irrespective of location" — so
        // schools and diaspora families enter and vote across the whole platform.
        Schema::create('competitions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('season'); // academic year, e.g. 2026
            $table->text('description')->nullable();
            // draft → open (accepting entries) → voting → closed (judged).
            $table->string('status')->default('draft');
            $table->dateTime('submissions_close_at')->nullable();
            $table->dateTime('voting_closes_at')->nullable();
            // Learners must have this much activity to qualify (BRD: min 3 months).
            $table->unsignedInteger('min_activity_days')->default(90);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitions');
    }
};
