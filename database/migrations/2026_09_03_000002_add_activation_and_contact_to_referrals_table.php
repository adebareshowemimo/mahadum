<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            // Which channel the referrer used to invite this person, copied from
            // the matched referral_invitations row at sign-up.
            $table->string('contact_channel')->nullable()->after('referred_subscription_id'); // email|phone
            $table->string('contact_value')->nullable()->after('contact_channel');
            $table->foreignId('referral_invitation_id')->nullable()->after('contact_value')
                ->constrained('referral_invitations')->nullOnDelete();

            // Activation gate progress (FR: paid subscription + 1 lesson + 1 quiz).
            $table->dateTime('first_lesson_completed_at')->nullable()->after('signed_up_at');
            $table->dateTime('first_quiz_completed_at')->nullable()->after('first_lesson_completed_at');
            $table->dateTime('activated_at')->nullable()->after('first_quiz_completed_at');

            $table->index('contact_value');
            $table->index('activated_at');
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referral_invitation_id');
            $table->dropColumn([
                'contact_channel',
                'contact_value',
                'first_lesson_completed_at',
                'first_quiz_completed_at',
                'activated_at',
            ]);
        });
    }
};
