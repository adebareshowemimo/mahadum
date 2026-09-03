<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_code_id')->constrained('referral_codes')->cascadeOnDelete();
            $table->foreignId('inviter_user_id')->nullable()->constrained('users')->nullOnDelete();
            // How the invite was sent — the referred person is matched back to it
            // by this contact at sign-up so the dashboards can show "via email"
            // vs "via phone".
            $table->string('channel'); // email|phone
            $table->string('contact'); // normalized email or phone
            $table->string('status')->default('sent'); // sent|accepted|blocked_existing
            $table->foreignId('accepted_referral_id')->nullable()->constrained('referrals')->nullOnDelete();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['referral_code_id', 'contact']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_invitations');
    }
};
