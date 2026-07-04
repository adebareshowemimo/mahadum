<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_redemptions', function (Blueprint $table) {
            // Consumer (non-institution) redemptions: who redeemed + which sub.
            $table->foreignId('user_id')->nullable()->after('organization_id')->constrained('users')->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->after('user_id')->constrained('subscriptions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('promo_redemptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_id');
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
