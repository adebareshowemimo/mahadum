<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hearts', function (Blueprint $table): void {
            $table->dateTime('competitive_paused_until')->nullable()->after('refills_at');
        });

        Schema::table('families', function (Blueprint $table): void {
            $table->string('timezone', 64)->default('Africa/Lagos')->after('child_limit');
        });

        Schema::table('learner_profiles', function (Blueprint $table): void {
            $table->unsignedInteger('current_level')->default(0)->change();
        });

        Schema::create('family_hero_awards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignId('learner_profile_id')->constrained('learner_profiles')->cascadeOnDelete();
            $table->date('award_date');
            $table->unsignedInteger('xp_earned');
            $table->timestamps();
            $table->unique(['family_id', 'learner_profile_id', 'award_date'], 'family_hero_daily_unique');
        });

        Schema::create('tone_practice_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('learner_profile_id')->constrained('learner_profiles')->cascadeOnDelete();
            $table->foreignId('lesson_component_id')->constrained('lesson_components')->cascadeOnDelete();
            $table->foreignId('inviter_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('channel', 20)->default('email');
            $table->dateTime('expires_at');
            $table->dateTime('opened_at')->nullable();
            $table->dateTime('accepted_at')->nullable();
            $table->timestamps();
        });

        DB::table('learner_profiles')->orderBy('id')->each(function (object $learner): void {
            $xp = (int) DB::table('xp_ledger')->where('learner_profile_id', $learner->id)->sum('amount');
            $level = match (true) {
                $xp >= 10000 => 5,
                $xp >= 4000 => 4,
                $xp >= 1500 => 3,
                $xp >= 500 => 2,
                $xp >= 100 => 1,
                default => 0,
            };
            DB::table('learner_profiles')->where('id', $learner->id)->update(['current_level' => $level]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tone_practice_invitations');
        Schema::dropIfExists('family_hero_awards');

        Schema::table('learner_profiles', function (Blueprint $table): void {
            $table->unsignedInteger('current_level')->default(1)->change();
        });
        Schema::table('families', fn (Blueprint $table) => $table->dropColumn('timezone'));
        Schema::table('hearts', fn (Blueprint $table) => $table->dropColumn('competitive_paused_until'));
    }
};
