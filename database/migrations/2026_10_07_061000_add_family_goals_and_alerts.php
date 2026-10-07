<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_coin_pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('goal_coins');
            $table->timestamps();
        });
        Schema::create('family_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->unsignedInteger('target_lessons');
            $table->json('learner_ids');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();
        });
        Schema::create('family_pool_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->restrictOnDelete();
            $table->foreignId('family_coin_pool_id')->constrained()->restrictOnDelete();
            $table->string('request_key', 100);
            $table->string('fingerprint', 64);
            $table->string('direction');
            $table->unsignedInteger('coins');
            $table->foreignId('learner_profile_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['family_id', 'request_key']);
        });
        Schema::create('family_cheers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learner_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('week_start');
            $table->string('message');
            $table->timestamps();
            $table->unique(['learner_profile_id', 'sender_user_id', 'week_start'], 'family_weekly_cheer_unique');
        });
        Schema::create('family_alert_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('low_balance_coins')->nullable();
            $table->unsignedInteger('inactive_days')->nullable();
            $table->boolean('review_alerts')->default(false);
            $table->timestamps();
        });
        Schema::create('family_alert_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('episode')->nullable();
            $table->timestamps();
            $table->unique(['family_id', 'key']);
        });
    }

    public function down(): void
    {
        foreach (['family_alert_states', 'family_alert_preferences', 'family_cheers', 'family_pool_movements', 'family_challenges', 'family_coin_pools'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
